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

try:
    import pyamf
    from pyamf import remoting
except ImportError:
    sys.stderr.write("ERROR: pyamf not installed. Run: pip install pyamf\n")
    sys.exit(1)


def recursive_extract(obj, buildings, specialists, buffs, resources, friends, players, zone_info, visited=None):
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
            if building:
                buildings.append(building)

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
                'playerLevel', 'level', 'friendSince', 'onlineStatus',
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
                         'currentMaximumBuildingsCountAll', 'availableBuffs_vector', 'onlineStatus']:
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
                        for fattr in ['username', 'nickname', 'playerLevel', 'level', 'avatarId', 'onlineStatus']:
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
                    friends, players, zone_info, visited
                )
        except Exception:
            pass

    # Recurse into attributes
    if hasattr(obj, '__dict__'):
        for key, value in obj.__dict__.items():
            if value is not None:
                recursive_extract(value, buildings, specialists, buffs, resources, friends, players, zone_info, visited)

    # Recurse into lists / tuples
    if isinstance(obj, (list, tuple, pyamf.ASObject if hasattr(pyamf, 'ASObject') else list)):
        iterable = obj.items() if isinstance(obj, dict) else enumerate(obj)
        for _, value in iterable:
            if value is not None:
                recursive_extract(value, buildings, specialists, buffs, resources, friends, players, zone_info, visited)

    # Recurse into dicts
    if isinstance(obj, dict):
        for key, value in obj.items():
            if value is not None:
                recursive_extract(value, buildings, specialists, buffs, resources, friends, players, zone_info, visited)


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
            recursive_extract(message.body, buildings, specialists, buffs, resources, friends, players, zone_info)
        else:
            recursive_extract(message, buildings, specialists, buffs, resources, friends, players, zone_info)

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
        'errorCode': error_code,
        'visitors': make_serializable(visitors)
    }

    # Write the entire raw envelope tree as JSON for debugging fields
    try:
        import os
        envelope_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'debug_envelope.json')
        with open(envelope_path, 'w', encoding='utf-16') as debug_f:
            json.dump(make_serializable(envelope), debug_f, ensure_ascii=False, indent=2)
    except Exception as e:
        sys.stderr.write(f"Warning: Could not save raw debug envelope: {e}\n")

    print(json.dumps(result, ensure_ascii=False, indent=2))


if __name__ == '__main__':
    main()
