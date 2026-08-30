import base64, struct, json, sys

B64 = "AAMAAAABAAwvNDgvb25SZXN1bHQAAP////8RCoEDVWZsZXgubWVzc2FnaW5nLm1lc3NhZ2VzLkFja25vd2xlZGdlTWVzc2FnZQlib2R5EWNsaWVudElkG2NvcnJlbGF0aW9uSWQXZGVzdGluYXRpb24PaGVhZGVycxNtZXNzYWdlSWQVdGltZVRvTGl2ZRN0aW1lc3RhbXAKM1lkZWZhdWx0R2FtZS5Db21tdW5pY2F0aW9uLlZPLmRTZXJ2ZXJSZXNwb25zZQl0eXBlDXpvbmVJRAlkYXRhBFsE4d8ICjNhZGVmYXVsdEdhbWUuQ29tbXVuaWNhdGlvbi5WTy5kU2VydmVyQWN0aW9uUmVzdWx0FWNsaWVudFRpbWUTZXJyb3JDb2RlGAVCEbqHPVAAAAQAClNfZGVmYXVsdEdhbWUuQ29tbXVuaWNhdGlvbi5WTy5kR2FtZVRpY2tDb21tYW5kVk8RcGxheWVySUQJdGltZQltb2RlGBF1bmlxdWVJRATh3wgFQhG6h2hIAAAEWwoHQ2ZsZXgubWVzc2FnaW5nLmlvLkFycmF5Q29sbGVjdGlvbgkDAQqBY19kZWZhdWx0R2FtZS5Db21tdW5pY2F0aW9uLlZPLmRUaW1lZFByb2R1Y3Rpb25WTxF1bmlxdWVJZBFwbGF5ZXJJZB1wcm9kdWN0aW9uVHlwZRd0eXBlX3N0cmluZw1hbW91bnQbcHJvZHVjZWRJdGVtcxtjb2xsZWN0ZWRUaW1lL21vZGlmaWVkUHJvZHVjdGlvbkFkZGVyOW1vZGlmaWVkUHJvZHVjdGlvbk11bHRpcGxpZXI9bW9kaWZpZWRJbnN0YW50RmluaXNoQ29zdEFkZGVyR21vZGlmaWVkSW5zdGFudEZpbmlzaENvc3RNdWx0aXBsaWVyDXN0YWNrcwtpbmRleBlidWlsZGluZ0dyaWQKI01kZWZhdWx0R2FtZS5Db21tdW5pY2F0aW9uLlZPLmRVbmlxdWVJRBN1bmlxdWVJRDETdW5pcXVlSUQyBILuBQQABOHfCAQBBkFFdmVudE1vbnN0ZXJCdWZmRHJpbGxNYW51YWxSb3VnaAQBBAAFAAAAAAAAAAAEAAU/8AAAAAAAAAQABT/wAAAAAAAABAEEAATGKQEGSWYwODVjNTAwLTY5YjMtNGFhZC05MGE5LTUwMjQ0ZDgwOWEyNQZJMmI4YWEyNWEtNTc1Mi00ZTNjLTkxMjMtOGQwNGVhM2RiYTM5AQoLAQEGSTAzMWJlNjMxLTUzYTItNDE2ZC04YWNkLWMyM2UzYTE1MDM4YQUAAAAAAAAAAAVCegSZkc8QAA=="

data = base64.b64decode(B64)
print("total bytes:", len(data))


class R:
    def __init__(self, b):
        self.b = b
        self.i = 0
        self.strs = []
        self.objs = []
        self.traits = []

    def u8(self):
        v = self.b[self.i]
        self.i += 1
        return v

    def u16(self):
        v = struct.unpack_from(">H", self.b, self.i)[0]
        self.i += 2
        return v

    def u32(self):
        v = struct.unpack_from(">I", self.b, self.i)[0]
        self.i += 4
        return v

    def u29(self):
        v = 0
        for n in range(3):
            byte = self.u8()
            v = (v << 7) | (byte & 0x7F)
            if not byte & 0x80:
                return v
        byte = self.u8()
        return (v << 8) | byte

    def d64(self):
        v = struct.unpack_from(">d", self.b, self.i)[0]
        self.i += 8
        return v

    def s(self):
        h = self.u29()
        if h & 1 == 0:
            return self.strs[h >> 1]
        ln = h >> 1
        if ln == 0:
            return ""
        raw = self.b[self.i:self.i + ln]
        self.i += ln
        v = raw.decode("utf-8", "replace")
        self.strs.append(v)
        return v

    def val(self):
        m = self.u8()
        if m == 0x00:
            return None            # undefined
        if m == 0x01:
            return None            # null
        if m == 0x02:
            return False
        if m == 0x03:
            return True
        if m == 0x04:
            return self.u29()      # integer
        if m == 0x05:
            return self.d64()      # double
        if m == 0x06:
            return self.s()        # string
        if m == 0x08:              # date
            h = self.u29()
            if h & 1 == 0:
                return self.objs[h >> 1]
            v = {"__date_ms": self.d64()}
            self.objs.append(v)
            return v
        if m == 0x09:
            return self.array()
        if m == 0x0A:
            return self.obj()
        if m == 0x0C:              # byte array
            h = self.u29()
            ln = h >> 1
            raw = self.b[self.i:self.i + ln]
            self.i += ln
            return {"__bytes": ln}
        raise ValueError(f"marker 0x{m:02x} at {self.i - 1}")

    def array(self):
        h = self.u29()
        if h & 1 == 0:
            return self.objs[h >> 1]
        dense = h >> 1
        out = []
        self.objs.append(out)
        assoc = {}
        while True:
            k = self.s()
            if k == "":
                break
            assoc[k] = self.val()
        for _ in range(dense):
            out.append(self.val())
        if assoc:
            out.append({"__assoc": assoc})
        return out

    def obj(self):
        h = self.u29()
        if h & 1 == 0:
            return self.objs[h >> 1]
        h >>= 1
        if h & 1 == 0:             # trait reference
            tr = self.traits[h >> 1]
        else:
            h >>= 1
            ext = bool(h & 1)
            h >>= 1
            dyn = bool(h & 1)
            cnt = h >> 1
            cls = self.s()
            props = [self.s() for _ in range(cnt)]
            tr = {"cls": cls, "props": props, "dyn": dyn, "ext": ext}
            self.traits.append(tr)
        o = {"__class": tr["cls"] or "Object"}
        self.objs.append(o)
        if tr["ext"]:
            o["__externalizable"] = True
            o["__value"] = self.val()
            return o
        for p in tr["props"]:
            o[p] = self.val()
        if tr["dyn"]:
            while True:
                k = self.s()
                if k == "":
                    break
                o[k] = self.val()
        return o


# ---- AMF0 packet envelope ----
r = R(data)
ver = r.u16()
hcount = r.u16()
print("amf version:", ver, "header count:", hcount)
for _ in range(hcount):
    n = r.b[r.i + 2:r.i + 2 + r.u16()]
    raise SystemExit("unexpected headers")
bcount = r.u16()
print("body count:", bcount)
for _ in range(bcount):
    tln = r.u16()
    target = r.b[r.i:r.i + tln].decode()
    r.i += tln
    rln = r.u16()
    resp = r.b[r.i:r.i + rln].decode()
    r.i += rln
    blen = r.u32()
    print(f"target={target!r} response={resp!r} declared_len={blen}")
    marker = r.u8()
    print("first body marker:", hex(marker), "(0x11 = switch to AMF3)")
    val = r.val()
    print(json.dumps(val, indent=2, ensure_ascii=False))
