#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
parse_zone.py - Parse raw AMF zone response from TSO game server.

Usage: python parse_zone.py <amf_file>

Uses pyamf (install: pip install pyamf) to decode the AMF binary,
then recursively extracts buildings, specialists, buffs, resources,
player info, friends, and zone metrics.

Output: JSON to stdout with keys: buildings, specialists, buffs, resources,
        friends, level, xp, resourceLimit, avatarId, playerNickname, resources,
        friends, level, xp, resourceLimit, avatarId, playerNickname
"""

import sys
import json
import re
import warnings

warnings.filterwarnings("ignore")

try:
    import pyamf
    from pyamf import remoting
except ImportError:
    sys.stderr.write("ERROR: pyamf not installed. Run: pip install pyamf\n")
    sys.exit(1)


def _attr(obj, *names, default=None):
    """Read the first existing attribute / dict key out of `names`."""
    for name in names:
        if hasattr(obj, name):
            val = getattr(obj, name)
            if val is not None:
                return val
        elif isinstance(obj, dict) and name in obj:
            val = obj[name]
            if val is not None:
                return val
    return default


def _as_items(container):
    """Unwrap a Flex ArrayCollection / list into a plain iterable."""
    if container is None:
        return []
    if hasattr(container, 'source'):
        try:
            source = container.source
            if source is not None:
                container = source
        except Exception:
            return []
    if isinstance(container, (list, tuple)):
        return container
    return []


# Island collectibles are ordinary buildings whose name is registered in
# collections.xml (CollectionsManager.buildingToResources). The client decides
# with getBuildingIsNormalCollectible() / getBuildingIsEventCollectible() and
# then stores them in pickups[type][grid] - see cZone.AddBuildingToList().
# dZoneVO.pickups itself is not filled by the server, so it cannot be used.
COLLECTIBLE_BUILDING_PATTERNS = (
    # CollectibleHerbsBuilding, CollectibleWineBarrelBuilding, ...
    (re.compile(r'^Collectible.+Building$', re.I), 0),
    # Event collectibles: Starfall motes and friends.
    (re.compile(r'^StarfallStarDust.*$', re.I), 1),
)


def collectible_type(building_name):
    """Return COLLECTIBLE_BUILDING_NORMAL (0) / _EVENT (1), or None."""
    if not building_name:
        return None

    for pattern, ptype in COLLECTIBLE_BUILDING_PATTERNS:
        if pattern.match(str(building_name)):
            return ptype

    return None


def pickups_from_buildings(buildings):
    """Derive the collectible list from the extracted buildings.

    Mirrors cZone.AddBuildingToList(): a building whose name is a registered
    collectible becomes a pickup, addressed by its own dUniqueID.
    """
    pickups = []

    for b in buildings:
        name = b.get('buildingName_string') or b.get('buildingName') or ''
        ptype = collectible_type(name)
        if ptype is None:
            continue

        try:
            grid = int(b.get('buildingGrid') or b.get('grid') or 0)
        except (TypeError, ValueError):
            continue

        # The grid identifies the building for COMMAND.DESTRUCT_BUILDING (65),
        # which is what a click on a collectible actually sends.
        if grid <= 0:
            continue

        try:
            uid1 = int(b.get('uniqueId1') or 0)
            uid2 = int(b.get('uniqueId2') or 0)
        except (TypeError, ValueError):
            uid1, uid2 = 0, 0

        pickups.append({
            'unique_id1': uid1,
            'unique_id2': uid2,
            'type': ptype,
            'resource': str(name),
            'building_name': str(name),
            'grid': grid,
        })

    return pickups


def extract_pickups(zone_obj):
    """Extract dZoneVO.pickups (island collectibles) as flat dicts.

    Each entry carries the dUniqueID needed by COMMAND.EXECUTE_PICKUP (13002),
    plus the collectible type (COLLECTIBLE_BUILDING_NORMAL = 0 /
    COLLECTIBLE_BUILDING_EVENT = 1), resource name and grid.
    """
    pickups = []

    for p in _as_items(_attr(zone_obj, 'pickups')):
        if p is None:
            continue

        uid = _attr(p, 'uniqueID', 'uniqueId', 'uid')

        uid1 = _attr(uid, 'uniqueID1', 'uniqueId1', default=None) if uid is not None else None
        uid2 = _attr(uid, 'uniqueID2', 'uniqueId2', default=None) if uid is not None else None

        if uid1 is None:
            uid1 = _attr(p, 'uniqueID1', 'uniqueId1', default=0)
        if uid2 is None:
            uid2 = _attr(p, 'uniqueID2', 'uniqueId2', default=0)

        try:
            uid1 = int(uid1 or 0)
            uid2 = int(uid2 or 0)
        except (TypeError, ValueError):
            continue

        if uid1 == 0 and uid2 == 0:
            continue

        try:
            ptype = int(_attr(p, 'type', 'providerType', 'collectibleType', default=0) or 0)
        except (TypeError, ValueError):
            ptype = 0

        try:
            grid = int(_attr(p, 'grid', 'buildingGrid', default=0) or 0)
        except (TypeError, ValueError):
            grid = 0

        pickups.append({
            'unique_id1': uid1,
            'unique_id2': uid2,
            'type': ptype,
            'resource': str(_attr(
                p, 'resourceName_string', 'item_string', 'resourceName',
                'buildingName_string', 'name_string', 'name', default='') or ''),
            'grid': grid,
        })

    return pickups


def extract_production_queues(zone_obj):
    """Extract dZoneVO.timedProductions_vector as a list of non-empty queue state dicts.

    Returns:
        None if timedProductions_vector is missing from zone_obj (unparseable / old snapshot).
        [] if timedProductions_vector is present but contains no orders across all queues.
        list of { 'production_type': int, 'orders': list[dict] } for queues with orders.
    """
    tp_container = _attr(zone_obj, 'timedProductions_vector', 'timedProductions')
    if tp_container is None:
        return None

    collections = _as_items(tp_container)
    queues = []

    for col in collections:
        orders_raw = _as_items(col)
        if not orders_raw:
            continue

        orders = []
        production_type = None

        for item in orders_raw:
            if item is None:
                continue

            ptype = _attr(item, 'productionType')
            if production_type is None and ptype is not None:
                try:
                    production_type = int(ptype)
                except (TypeError, ValueError):
                    pass

            t_str = str(_attr(item, 'type_string', 'typeString', 'type', default='') or '')

            def _to_int(val, default=0):
                try:
                    return int(val)
                except (TypeError, ValueError):
                    return default

            def _to_float(val, default=0.0):
                try:
                    return float(val)
                except (TypeError, ValueError):
                    return default

            orders.append({
                'type_string': t_str,
                'amount': _to_int(_attr(item, 'amount'), 1),
                'produced_items': _to_int(_attr(item, 'producedItems', 'produced_items'), 0),
                'collected_time': _to_float(_attr(item, 'collectedTime', 'collected_time'), 0.0),
                'stacks': _to_int(_attr(item, 'stacks'), 1),
                'index': _to_int(_attr(item, 'index'), 0),
            })

        if production_type is not None and orders:
            queues.append({
                'production_type': production_type,
                'orders': orders,
            })

    return queues


def recursive_extract(obj, buildings, specialists, buffs, resources, friends, players, zone_info, deposits, build_queue, visited=None):
    """Recursively walk the decoded AMF object tree and extract VOs."""
    if visited is None:
        visited = set()

    obj_id = id(obj)
    if obj_id in visited:
        return
    visited.add(obj_id)

    if hasattr(obj, '__class__'):
        class_name = getattr(obj, '__class__', type(obj)).__name__
        type_name = ''
        if hasattr(obj, '__amf__'):
            try:
                amf_meta = getattr(obj, '__amf__')
                if isinstance(amf_meta, dict):
                    type_name = amf_meta.get('alias', '')
                elif hasattr(amf_meta, 'alias'):
                    type_name = getattr(amf_meta, 'alias', '')
                elif hasattr(amf_meta, 'get'):
                    type_name = amf_meta.get('alias', '')
            except Exception:
                pass

        # Also check the pyamf alias
        alias = ''
        try:
            alias = str(pyamf.get_class_alias(type(obj)).alias)
        except (pyamf.UnknownClassAlias, AttributeError):
            pass

        direct_alias = getattr(obj, 'alias', '')
        full_name = str(direct_alias or alias or type_name or class_name)

        if 'dBuildingVO' in full_name or 'BuildingVO' in full_name:
            building = {}
            for attr in ['buildingName_string', 'buildingGrid', 'isProductionActive',
                         'upgradeLevel', 'buildingMode', 'buildingName', 'grid', 'buffs',
                         'upgradeIsInProgress', 'level', 'isBought']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    building[attr] = val

            # dBuildingVO.uniqueId:dUniqueID - required to click collectibles
            # with COMMAND.EXECUTE_PICKUP (13002).
            b_uid = _attr(obj, 'uniqueId', 'uniqueID', 'uid')
            b_uid1 = _attr(b_uid, 'uniqueID1', 'uniqueId1', default=None) if b_uid is not None else None
            b_uid2 = _attr(b_uid, 'uniqueID2', 'uniqueId2', default=None) if b_uid is not None else None

            if b_uid1 is None:
                b_uid1 = _attr(obj, 'uniqueID1', 'uniqueId1', default=None)
            if b_uid2 is None:
                b_uid2 = _attr(obj, 'uniqueID2', 'uniqueId2', default=None)

            if b_uid1 is not None:
                building['uniqueId1'] = b_uid1
            if b_uid2 is not None:
                building['uniqueId2'] = b_uid2

            if building:
                buildings.append(building)

        elif 'dDepositVO' in full_name or 'DepositVO' in full_name:
            deposit = {}
            for attr in ['gridIdx', 'name_string', 'name', 'amount', 'maxAmount',
                         'accessible', 'refillable', 'emptied', 'depositGroupdId']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    deposit[attr] = val

            grid_raw = deposit.get('gridIdx')
            name_raw = deposit.get('name_string') or deposit.get('name')

            if isinstance(name_raw, bytes):
                name_raw = name_raw.decode('utf-8', errors='replace')

            try:
                grid_val = int(grid_raw) if grid_raw is not None else 0
            except (TypeError, ValueError):
                grid_val = 0

            def _int_or(value, fallback=0):
                try:
                    return int(value)
                except (TypeError, ValueError):
                    return fallback

            if grid_val > 0 and name_raw:
                deposits.append({
                    'grid': grid_val,
                    'name': str(name_raw),
                    'amount': _int_or(deposit.get('amount')),
                    'max_amount': _int_or(deposit.get('maxAmount')),
                    'accessible': (_int_or(deposit['accessible'], -1)
                                   if deposit.get('accessible') is not None else None),
                    'refillable': bool(deposit.get('refillable') or False),
                    'emptied': _int_or(deposit.get('emptied')),
                })

        elif 'dBuildQueueVO' in full_name or 'BuildQueueVO' in full_name:
            b_list = None
            if hasattr(obj, 'buildings'):
                b_list = getattr(obj, 'buildings')
            elif isinstance(obj, dict) and 'buildings' in obj:
                b_list = obj['buildings']

            queue_buildings = []
            if b_list is not None:
                if hasattr(b_list, 'source'):
                    queue_buildings = b_list.source or []
                elif isinstance(b_list, (list, tuple)):
                    queue_buildings = b_list

            max_count = None
            for attr in ['maxCount', 'max_count']:
                if hasattr(obj, attr):
                    max_count = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    max_count = obj[attr]

            perm_count = None
            for attr in ['permanentSlotsCount', 'permanent_slots_count']:
                if hasattr(obj, attr):
                    perm_count = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    perm_count = obj[attr]

            temp_count = None
            for attr in ['tempSlotsCount', 'temp_slots_count']:
                if hasattr(obj, attr):
                    temp_count = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    temp_count = obj[attr]

            total_slots = int(max_count if max_count is not None else 3) + int(perm_count or 0) + int(temp_count or 0)
            build_queue['used'] = len(queue_buildings)
            build_queue['total'] = max(total_slots, 3)

        elif 'dSpecialistVO' in full_name or 'SpecialistVO' in full_name:
            specialist = {}

            # Extract type
            spec_type = None
            for attr in ['specialistType', 'type']:
                if hasattr(obj, attr):
                    spec_type = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    spec_type = obj[attr]
            if spec_type is not None:
                specialist['type'] = spec_type

            # Extract name
            spec_name = None
            for attr in ['name_string', 'name']:
                if hasattr(obj, attr):
                    spec_name = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    spec_name = obj[attr]
            if spec_name is not None:
                specialist['name'] = spec_name

            # Extract uniqueId1 and uniqueId2
            uid_obj = None
            for attr in ['uniqueID', 'uniqueId']:
                if hasattr(obj, attr):
                    uid_obj = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    uid_obj = obj[attr]

            uid1, uid2 = None, None
            if uid_obj is not None:
                for attr in ['uniqueID1', 'uniqueId1']:
                    if hasattr(uid_obj, attr):
                        uid1 = getattr(uid_obj, attr)
                    elif isinstance(uid_obj, dict) and attr in uid_obj:
                        uid1 = uid_obj[attr]
                for attr in ['uniqueID2', 'uniqueId2']:
                    if hasattr(uid_obj, attr):
                        uid2 = getattr(uid_obj, attr)
                    elif isinstance(uid_obj, dict) and attr in uid_obj:
                        uid2 = uid_obj[attr]

            # Fallback to direct fields
            if uid1 is None:
                for attr in ['uniqueID1', 'uniqueId1']:
                    if hasattr(obj, attr):
                        uid1 = getattr(obj, attr)
                    elif isinstance(obj, dict) and attr in obj:
                        uid1 = obj[attr]
            if uid2 is None:
                for attr in ['uniqueID2', 'uniqueId2']:
                    if hasattr(obj, attr):
                        uid2 = getattr(obj, attr)
                    elif isinstance(obj, dict) and attr in obj:
                        uid2 = obj[attr]

            if uid1 is not None:
                specialist['uniqueId1'] = uid1
            if uid2 is not None:
                specialist['uniqueId2'] = uid2
            if uid1 is not None and uid2 is not None:
                specialist['uniqueId'] = f"{uid1}_{uid2}"

            # Extract task details
            task_obj = None
            for attr in ['task']:
                if hasattr(obj, attr):
                    task_obj = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    task_obj = obj[attr]

            if task_obj is not None:
                for attr in ['taskType', 'type']:
                    if hasattr(task_obj, attr):
                        specialist['taskType'] = getattr(task_obj, attr)
                    elif isinstance(task_obj, dict) and attr in task_obj:
                        specialist['taskType'] = task_obj[attr]
                for attr in ['taskSubType', 'subType']:
                    if hasattr(task_obj, attr):
                        specialist['taskSubType'] = getattr(task_obj, attr)
                    elif isinstance(task_obj, dict) and attr in task_obj:
                        specialist['taskSubType'] = task_obj[attr]
                for attr in ['taskEndTime', 'endTime']:
                    if hasattr(task_obj, attr):
                        specialist['taskEndTime'] = getattr(task_obj, attr)
                    elif isinstance(task_obj, dict) and attr in task_obj:
                        specialist['taskEndTime'] = task_obj[attr]

            # Fallback to direct fields
            for attr in ['taskType', 'taskSubType', 'taskEndTime']:
                if attr not in specialist:
                    val = None
                    if hasattr(obj, attr):
                        val = getattr(obj, attr)
                    elif isinstance(obj, dict) and attr in obj:
                        val = obj[attr]
                    if val is not None:
                        specialist[attr] = val

            if specialist:
                specialists.append(specialist)

        elif 'dBuffVO' in full_name or 'BuffVO' in full_name:
            buff = {}
            for attr in ['name', 'uniqueId', 'uniqueId1', 'uniqueId2', 'uniqueID1', 'uniqueID2',
                         'amount', 'buffId', 'type']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    buff[attr] = val

            # Extract uniqueId1 and uniqueId2 from nested uniqueID object if present
            uid_obj = None
            for attr in ['uniqueID', 'uniqueId']:
                if hasattr(obj, attr):
                    uid_obj = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    uid_obj = obj[attr]

            uid1, uid2 = None, None
            if uid_obj is not None:
                for attr in ['uniqueID1', 'uniqueId1']:
                    if hasattr(uid_obj, attr):
                        uid1 = getattr(uid_obj, attr)
                    elif isinstance(uid_obj, dict) and attr in uid_obj:
                        uid1 = uid_obj[attr]
                for attr in ['uniqueID2', 'uniqueId2']:
                    if hasattr(uid_obj, attr):
                        uid2 = getattr(uid_obj, attr)
                    elif isinstance(uid_obj, dict) and attr in uid_obj:
                        uid2 = uid_obj[attr]

            if uid1 is not None and 'uniqueId1' not in buff:
                buff['uniqueId1'] = uid1
            if uid2 is not None and 'uniqueId2' not in buff:
                buff['uniqueId2'] = uid2
            if 'uniqueId1' not in buff and 'uniqueID1' in buff:
                buff['uniqueId1'] = buff['uniqueID1']
            if 'uniqueId2' not in buff and 'uniqueID2' in buff:
                buff['uniqueId2'] = buff['uniqueID2']

            if buff:
                buffs.append(buff)

        elif 'dResourceVO' in full_name or 'ResourceVO' in full_name:
            resource = {}
            for attr in ['name', 'name_string', 'amount', 'producedAmount', 'type']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    resource[attr] = val
            if resource:
                resources.append(resource)

        elif 'dPlayerListItemVO' in full_name or 'PlayerListItemVO' in full_name:
            # GET_FRIEND_LIST returns dPlayerListVO.players containing
            # dPlayerListItemVO objects rather than dPlayerVO objects.
            friend = {}
            for attr in [
                'id', 'avatarId', 'username', 'username_string', 'nickname',
                'playerLevel', 'level', 'friendSince',
                'adventureVO'
            ]:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    friend[attr] = val

            # Entries with a negative ID represent adventures, not friends.
            friend_id = friend.get('id')
            is_adventure = (
                isinstance(friend_id, (int, float))
                and not isinstance(friend_id, bool)
                and friend_id < 0
            )

            if friend and not is_adventure:
                friend_name = (
                    friend.get('username')
                    or friend.get('username_string')
                    or friend.get('nickname')
                )
                exists = False
                for existing in friends:
                    existing_id = existing.get('id')
                    existing_name = (
                        existing.get('username')
                        or existing.get('username_string')
                        or existing.get('nickname')
                    )
                    if friend_id is not None and existing_id == friend_id:
                        exists = True
                        break
                    if friend_name and existing_name == friend_name:
                        exists = True
                        break

                if not exists:
                    friends.append(friend)

        elif 'dPlayerVO' in full_name or 'PlayerVO' in full_name:
            player = {}
            for attr in ['avatarId', 'playerLevel', 'level', 'nickname', 'username', 'username_string', 'xp',
                         'userID', 'pvpLevel', 'admiralAmount', 'generalsAmount', 'explorersAmount', 'geologistsAmount',
                         'currentMaximumBuildingsCountAll', 'availableBuffs_vector']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    player[attr] = val
            if player:
                # Deduplicate by userID or nickname/username
                p_uid = player.get('userID')
                p_uname = player.get('username_string') or player.get('username') or player.get('nickname')
                exists = False
                for p in players:
                    if p_uid and p.get('userID') == p_uid:
                        exists = True
                        break
                    p_existing_name = p.get('username_string') or p.get('username') or p.get('nickname')
                    if p_uname and p_existing_name == p_uname:
                        exists = True
                        break
                if not exists:
                    players.append(player)

        elif 'dZoneVO' in full_name or 'ZoneVO' in full_name:
            for attr in ['resourceLimit', 'level', 'xp', 'gameWorldName']:
                val = None
                if hasattr(obj, attr):
                    val = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    val = obj[attr]

                if val is not None:
                    zone_info[attr] = val

            # Extract island collectibles (pickups) from zone
            zone_pickups = extract_pickups(obj)
            if zone_pickups or 'pickups' not in zone_info:
                existing = zone_info.setdefault('pickups', [])
                seen = {(e['unique_id1'], e['unique_id2']) for e in existing}
                for entry in zone_pickups:
                    key = (entry['unique_id1'], entry['unique_id2'])
                    if key not in seen:
                        seen.add(key)
                        existing.append(entry)

            # Extract production queues from zone
            p_queues = extract_production_queues(obj)
            if p_queues is not None or 'production_queues' not in zone_info:
                zone_info['production_queues'] = p_queues

            # Extract friends list from zone
            for attr in ['friends', 'friendList', 'friendVOs']:
                friends_list = None
                if hasattr(obj, attr):
                    friends_list = getattr(obj, attr)
                elif isinstance(obj, dict) and attr in obj:
                    friends_list = obj[attr]

                if friends_list:
                    items = []
                    if hasattr(friends_list, 'source'):
                        items = friends_list.source
                    elif isinstance(friends_list, (list, tuple)):
                        items = friends_list

                    for friend_obj in items:
                        if friend_obj is None:
                            continue
                        friend = {}
                        for fattr in ['username', 'nickname', 'playerLevel', 'level', 'avatarId']:
                            fval = None
                            if hasattr(friend_obj, fattr):
                                fval = getattr(friend_obj, fattr)
                            elif isinstance(friend_obj, dict) and fattr in friend_obj:
                                fval = friend_obj[fattr]

                            if fval is not None:
                                friend[fattr] = fval
                        if friend:
                            friends.append(friend)

    # Recurse into Flex ArrayCollection. PyAMF exposes its contents through
    # the source property, which is not guaranteed to be present in __dict__.
    if hasattr(obj, 'source') and not isinstance(obj, (str, bytes)):
        try:
            source = obj.source
            if source is not None and source is not obj:
                recursive_extract(
                    source, buildings, specialists, buffs, resources,
                    friends, players, zone_info, deposits, build_queue, visited=visited
                )
        except Exception:
            pass

    # Recurse into attributes
    if hasattr(obj, '__dict__'):
        for key, value in obj.__dict__.items():
            if value is not None:
                recursive_extract(value, buildings, specialists, buffs, resources, friends, players, zone_info, deposits, build_queue, visited=visited)

    # Recurse into lists / tuples
    if isinstance(obj, (list, tuple, pyamf.ASObject if hasattr(pyamf, 'ASObject') else list)):
        iterable = obj.items() if isinstance(obj, dict) else enumerate(obj)
        for _, value in iterable:
            if value is not None:
                recursive_extract(value, buildings, specialists, buffs, resources, friends, players, zone_info, deposits, build_queue, visited=visited)

    # Recurse into dicts
    if isinstance(obj, dict):
        for key, value in obj.items():
            if value is not None:
                recursive_extract(value, buildings, specialists, buffs, resources, friends, players, zone_info, deposits, build_queue, visited=visited)


def make_serializable(obj, visited=None):
    """Convert pyamf types to JSON-serializable Python types, handling circular references."""
    if visited is None:
        visited = set()

    obj_id = id(obj)
    if obj_id in visited:
        alias = getattr(obj, 'alias', '') or obj.__class__.__name__
        return f"<circular reference to {alias} id={obj_id}>"

    is_mutable = isinstance(obj, (dict, list, tuple)) or hasattr(obj, '__dict__') or hasattr(obj, 'source')
    if is_mutable:
        visited.add(obj_id)

    try:
        # Support flex ArrayCollection with source property
        if hasattr(obj, 'source') and not isinstance(obj, (str, bytes)):
            try:
                res = make_serializable(obj.source, visited)
                return res
            except Exception:
                pass

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
        sys.stderr.write("Usage: python parse_zone.py <amf_file>\n")
        sys.exit(1)

    amf_file = sys.argv[1]

    with open(amf_file, 'rb') as f:
        raw_data = f.read()

    try:
        envelope = remoting.decode(raw_data)
    except Exception as e:
        sys.stderr.write(f"Failed to decode AMF: {e}\n")
        sys.exit(1)

    buildings = []
    specialists = []
    buffs = []
    resources = []
    friends = []
    players = []
    zone_info = {}
    deposits = []
    build_queue = {}
    error_code = 0

    # Extract errorCode from dServerResponse first
    for target, message in envelope.bodies:
        if hasattr(message, 'body'):
            body = message.body
            # body -> AcknowledgeMessage.body -> dServerResponse (TypedObject/dict)
            if hasattr(body, 'body'):
                resp = body.body
                # TypedObject is a dict-like object
                resp_data = resp.get('data', None) if isinstance(resp, dict) else None
                if isinstance(resp_data, dict):
                    error_code = resp_data.get('errorCode', 0)

    # Walk through all bodies in the remoting envelope
    for target, message in envelope.bodies:
        if hasattr(message, 'body'):
            recursive_extract(message.body, buildings, specialists, buffs, resources, friends, players, zone_info, deposits, build_queue)
        else:
            recursive_extract(message, buildings, specialists, buffs, resources, friends, players, zone_info, deposits, build_queue)

    # Resource category mapping
    RESOURCE_CATEGORIES = {
        'Basic': ['Wood', 'Coal', 'Iron', 'Gold', 'Stone', 'Granite', 'Wheat', 'Flour', 'Bread', 'Beer',
                  'Fish', 'Meat', 'Corn', 'Water', 'Plant', 'Flower', 'Flowers', 'Wool', 'Cloth'],
        'Improved': ['Plank', 'Beam', 'Coke', 'Steel', 'MetalFrame', 'CutStone', 'Board', 'Barrel', 'Sack',
                     'Wagon', 'Wheel', 'Horse', 'Carriage', 'Furniture', 'Marble', 'Bronze', 'Titanium',
                     'Platinum', 'BronzeOre', 'GoldOre', 'IronOre', 'TitaniumOre', 'PlatinumOre',
                     'ObsidianOre', 'MountainOre', 'CrystalShard', 'Crystal', 'Grout', 'Oil', 'Oilseed',
                     'MahoganyPlank', 'MahoganyWood', 'ExoticPlank', 'ExoticWood', 'RealPlank', 'RealWood',
                     'SimplePaper', 'IntermediatePaper', 'AdvancedPaper', 'FireWood', 'DeadTreeWood'],
        'Advanced': ['Sword', 'Cannon', 'Musket', 'Crossbow', 'Longbow', 'Bow', 'IronSword', 'SteelSword',
                     'PlatinumSword', 'TitaniumSword', 'BronzeSword', 'Archebuse', 'CompositeBow', 'Pike',
                     'BattleLance', 'Saber', 'BattleHorse', 'Gunpowder', 'Salpeter', 'Mortar', 'Horse',
                     'Saddlecloth', 'Manuscript', 'Codex', 'Tome', 'BookFitting', 'PageOfSheetMusic',
                     'Nib', 'Tool', 'UsedHammer', 'AdvancedTools', 'GlowingHerbs', 'MagicBean',
                     'MagicBeanstalk', 'BridgeBaseMaterial', 'Wheel', 'SpottedMushroom', 'StinkyMushroom',
                     'HeartFruit', 'DeadCrops'],
        'Currency': ['Coin', 'StarCoin', 'GuildCoins', 'HardCurrency', 'Token', 'DefensePoint',
                     'ValorPoint', 'Population', 'GuildFestCommendation', 'MapPart'],
    }
    CATEGORY_ORDER = ['Basic', 'Improved', 'Advanced', 'Currency']

    def get_category(name):
        for cat in CATEGORY_ORDER:
            if name in RESOURCE_CATEGORIES[cat]:
                return cat
        return 'Other'

    # Add category to each resource
    for r in resources:
        rname = r.get('name_string') or r.get('name', '')
        r['category'] = get_category(rname)

    # Calculate storage capacity (resourceLimit) based on Mayorhouse and Storehouse upgrades
    calc_limit = 500 # default base limit

    for b in buildings:
        bname = b.get('buildingName_string') or b.get('buildingName', '')
        bname_lower = bname.lower()
        level_val = int(b.get('upgradeLevel') or b.get('level') or 1)

        if 'mayorhouse' in bname_lower or 'townhall' in bname_lower:
            caps = [0, 500, 2100, 3600, 6500, 10500, 16500, 25500]
            idx = min(level_val, len(caps) - 1)
            calc_limit += caps[idx]
        elif 'improvedstorehouse' in bname_lower or 'improvedwarehouse' in bname_lower:
            caps = [0, 300, 1500, 4500, 9000, 18000, 36000, 72000]
            idx = min(level_val, len(caps) - 1)
            calc_limit += caps[idx]
        elif 'spaciousstorehouse' in bname_lower or 'spaciouswarehouse' in bname_lower:
            caps = [0, 0, 1500, 4500, 10500, 22500, 48000]
            idx = min(level_val, len(caps) - 1)
            calc_limit += caps[idx]
        elif 'storehouse' in bname_lower or 'warehouse' in bname_lower:
            caps = [0, 100, 500, 1500, 3000, 6000, 12000, 20000]
            idx = min(level_val, len(caps) - 1)
            calc_limit += caps[idx]
        elif 'giantbarrel' in bname_lower:
            calc_limit += 40000
        elif 'mountainclancolossus' in bname_lower:
            calc_limit += 20000 * level_val
        elif 'starfallairship' in bname_lower:
            calc_limit += 500
        elif 'storagetower' in bname_lower:
            caps = [0, 1000, 2000, 3000, 5000, 8000, 16000, 28000]
            idx = min(level_val, len(caps) - 1)
            calc_limit += caps[idx]
        elif 'chocolatedepot' in bname_lower:
            caps = [0, 20000, 30000, 40000]
            idx = min(level_val, len(caps) - 1)
            calc_limit += caps[idx]
        elif 'depositorium' in bname_lower:
            caps = [0, 40000, 60000, 70000]
            idx = min(level_val, len(caps) - 1)
            calc_limit += caps[idx]

    # Collectibles come from the buildings list (see pickups_from_buildings);
    # anything the server happened to put into dZoneVO.pickups is merged in.
    merged_pickups = list(zone_info.get('pickups') or [])
    _seen_pickups = {
        entry.get('grid')
        for entry in merged_pickups
        if isinstance(entry, dict)
    }

    for entry in pickups_from_buildings(buildings):
        if entry['grid'] not in _seen_pickups:
            _seen_pickups.add(entry['grid'])
            merged_pickups.append(entry)

    owner_player = players[0] if len(players) > 0 else {}
    visitors = []
    if len(players) > 1:
        for p in players[1:]:
            pname = p.get('nickname') or p.get('username') or p.get('username_string')
            if pname:
                visitors.append({
                    'nickname': pname,
                    'avatarId': p.get('avatarId'),
                    'level': p.get('playerLevel') or p.get('level')
                })

    result = {
        'buildings': make_serializable(buildings),
        'specialists': make_serializable(specialists),
        'buffs': make_serializable(buffs),
        'resources': make_serializable(resources),
        'friends': make_serializable(friends),
        'players': make_serializable(players),
        'level': owner_player.get('playerLevel') or owner_player.get('level') or zone_info.get('level'),
        'xp': owner_player.get('xp') or zone_info.get('xp'),
        'pvpLevel': owner_player.get('pvpLevel'),
        'admiralAmount': owner_player.get('admiralAmount'),
        'generalsAmount': owner_player.get('generalsAmount'),
        'explorersAmount': owner_player.get('explorersAmount'),
        'geologistsAmount': owner_player.get('geologistsAmount'),
        'currentMaximumBuildingsCountAll': owner_player.get('currentMaximumBuildingsCountAll'),
        'availableBuffs': make_serializable(owner_player.get('availableBuffs_vector') or []),
        'resourceLimit': zone_info.get('resourceLimit') or calc_limit,
        'avatarId': owner_player.get('avatarId'),
        'playerNickname': owner_player.get('nickname') or owner_player.get('username') or owner_player.get('username_string'),
        'userID': owner_player.get('userID') or zone_info.get('zoneOwnerPlayerID'),
        'zoneOwnerPlayerID': zone_info.get('zoneOwnerPlayerID'),
        'gameWorldName': zone_info.get('gameWorldName'),
        'pickups': make_serializable(merged_pickups),
        'errorCode': error_code,
        'visitors': make_serializable(visitors),
        'deposits': deposits,
        'build_queue': {
            'used': int(build_queue.get('used', 0)),
            'total': int(build_queue.get('total', 0)),
        } if build_queue else None,
        'production_queues': make_serializable(zone_info['production_queues']) if zone_info.get('production_queues') is not None else None
    }

    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
