#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
parse_market.py - Parse raw AMF market updates response from TSO game server.

Usage: python parse_market.py <amf_file>
"""

import sys
import json

try:
    import pyamf
    from pyamf import remoting
except ImportError:
    sys.stderr.write("ERROR: pyamf not installed. Run: pip install pyamf\n")
    sys.exit(1)

def recursive_extract_offers(obj, offers, visited=None):
    if visited is None:
        visited = set()

    obj_id = id(obj)
    if obj_id in visited:
        return
    visited.add(obj_id)

    if hasattr(obj, '__class__'):
        class_name = getattr(obj, '__class__', type(obj)).__name__
        alias = ''
        try:
            alias = str(pyamf.get_class_alias(type(obj)).alias)
        except (pyamf.UnknownClassAlias, AttributeError):
            pass
        direct_alias = getattr(obj, 'alias', '')
        full_name = str(direct_alias or alias or class_name)

        if 'dTradeObjectVO' in full_name or 'TradeObjectVO' in full_name:
            offer = {}
            for attr in ['id', 'senderID', 'senderName', 'offer', 'type', 'created', 'lotsRemaining']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]
                
                if val is not None:
                    offer[attr] = val
            if offer:
                offers.append(offer)

    # Recurse attributes
    if hasattr(obj, '__dict__'):
        for key, value in obj.__dict__.items():
            if value is not None:
                recursive_extract_offers(value, offers, visited)

    # Recurse lists/tuples
    if isinstance(obj, (list, tuple)):
        for value in obj:
            if value is not None:
                recursive_extract_offers(value, offers, visited)

    # Recurse dicts
    if isinstance(obj, dict):
        for key, value in obj.items():
            if value is not None:
                recursive_extract_offers(value, offers, visited)

def make_serializable(obj, visited=None):
    if visited is None:
        visited = set()
    obj_id = id(obj)
    if obj_id in visited:
        return f"<circular reference id={obj_id}>"
    is_mutable = isinstance(obj, (dict, list, tuple)) or hasattr(obj, '__dict__')
    if is_mutable:
        visited.add(obj_id)
    try:
        if isinstance(obj, dict):
            return {str(k): make_serializable(v, visited) for k, v in obj.items()}
        elif isinstance(obj, (list, tuple)):
            return [make_serializable(v, visited) for v in obj]
        elif isinstance(obj, bytes):
            return obj.decode('utf-8', errors='replace')
        elif isinstance(obj, (int, float, str, bool, type(None))):
            return obj
        elif hasattr(obj, '__dict__'):
            return {str(k): make_serializable(v, visited) for k, v in obj.__dict__.items()}
        else:
            return str(obj)
    finally:
        if is_mutable and obj_id in visited:
            visited.remove(obj_id)

def main():
    if hasattr(sys.stdout, 'reconfigure'):
        sys.stdout.reconfigure(encoding='utf-8')
    if len(sys.argv) < 2:
        sys.stderr.write("Usage: python parse_market.py <amf_file>\n")
        sys.exit(1)

    amf_file = sys.argv[1]

    with open(amf_file, 'rb') as f:
        raw_data = f.read()

    try:
        envelope = remoting.decode(raw_data)
    except Exception as e:
        sys.stderr.write(f"Failed to decode AMF: {e}\n")
        sys.exit(1)

    offers = []
    current_time = 0
    error_code = 0

    # Walk through bodies
    for target, message in envelope.bodies:
        # Check current time and error code in response if present
        if hasattr(message, 'body'):
            body = message.body
            if hasattr(body, 'body'):
                resp = body.body
                
                data = None
                if isinstance(resp, dict):
                    data = resp.get('data', None)
                elif hasattr(resp, 'data'):
                    data = resp.data
                
                if data is not None:
                    if isinstance(data, dict):
                        current_time = data.get('currentTime', current_time)
                        error_code = data.get('errorCode', error_code)
                    else:
                        if hasattr(data, 'currentTime'):
                            current_time = getattr(data, 'currentTime')
                        if hasattr(data, 'errorCode'):
                            error_code = getattr(data, 'errorCode')
        
        if hasattr(message, 'body'):
            recursive_extract_offers(message.body, offers)
        else:
            recursive_extract_offers(message, offers)

    result = {
        'offers': make_serializable(offers),
        'currentTime': current_time,
        'errorCode': error_code
    }

    print(json.dumps(result, ensure_ascii=False, indent=2))

if __name__ == '__main__':
    main()
