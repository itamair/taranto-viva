(() => {
  var __create = Object.create;
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __getProtoOf = Object.getPrototypeOf;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __commonJS = (cb, mod) => function __require() {
    return mod || (0, cb[__getOwnPropNames(cb)[0]])((mod = { exports: {} }).exports, mod), mod.exports;
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
    isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
    mod
  ));

  // node_modules/ieee754/index.js
  var require_ieee754 = __commonJS({
    "node_modules/ieee754/index.js"(exports) {
      exports.read = function(buffer, offset, isLE, mLen, nBytes) {
        var e, m;
        var eLen = nBytes * 8 - mLen - 1;
        var eMax = (1 << eLen) - 1;
        var eBias = eMax >> 1;
        var nBits = -7;
        var i2 = isLE ? nBytes - 1 : 0;
        var d = isLE ? -1 : 1;
        var s = buffer[offset + i2];
        i2 += d;
        e = s & (1 << -nBits) - 1;
        s >>= -nBits;
        nBits += eLen;
        for (; nBits > 0; e = e * 256 + buffer[offset + i2], i2 += d, nBits -= 8) {
        }
        m = e & (1 << -nBits) - 1;
        e >>= -nBits;
        nBits += mLen;
        for (; nBits > 0; m = m * 256 + buffer[offset + i2], i2 += d, nBits -= 8) {
        }
        if (e === 0) {
          e = 1 - eBias;
        } else if (e === eMax) {
          return m ? NaN : (s ? -1 : 1) * Infinity;
        } else {
          m = m + Math.pow(2, mLen);
          e = e - eBias;
        }
        return (s ? -1 : 1) * m * Math.pow(2, e - mLen);
      };
      exports.write = function(buffer, value, offset, isLE, mLen, nBytes) {
        var e, m, c;
        var eLen = nBytes * 8 - mLen - 1;
        var eMax = (1 << eLen) - 1;
        var eBias = eMax >> 1;
        var rt = mLen === 23 ? Math.pow(2, -24) - Math.pow(2, -77) : 0;
        var i2 = isLE ? 0 : nBytes - 1;
        var d = isLE ? 1 : -1;
        var s = value < 0 || value === 0 && 1 / value < 0 ? 1 : 0;
        value = Math.abs(value);
        if (isNaN(value) || value === Infinity) {
          m = isNaN(value) ? 1 : 0;
          e = eMax;
        } else {
          e = Math.floor(Math.log(value) / Math.LN2);
          if (value * (c = Math.pow(2, -e)) < 1) {
            e--;
            c *= 2;
          }
          if (e + eBias >= 1) {
            value += rt / c;
          } else {
            value += rt * Math.pow(2, 1 - eBias);
          }
          if (value * c >= 2) {
            e++;
            c /= 2;
          }
          if (e + eBias >= eMax) {
            m = 0;
            e = eMax;
          } else if (e + eBias >= 1) {
            m = (value * c - 1) * Math.pow(2, mLen);
            e = e + eBias;
          } else {
            m = value * Math.pow(2, eBias - 1) * Math.pow(2, mLen);
            e = 0;
          }
        }
        for (; mLen >= 8; buffer[offset + i2] = m & 255, i2 += d, m /= 256, mLen -= 8) {
        }
        e = e << mLen | m;
        eLen += mLen;
        for (; eLen > 0; buffer[offset + i2] = e & 255, i2 += d, e /= 256, eLen -= 8) {
        }
        buffer[offset + i2 - d] |= s * 128;
      };
    }
  });

  // node_modules/pbf/index.js
  var require_pbf = __commonJS({
    "node_modules/pbf/index.js"(exports, module) {
      "use strict";
      module.exports = Pbf3;
      var ieee7542 = require_ieee754();
      function Pbf3(buf) {
        this.buf = ArrayBuffer.isView && ArrayBuffer.isView(buf) ? buf : new Uint8Array(buf || 0);
        this.pos = 0;
        this.type = 0;
        this.length = this.buf.length;
      }
      Pbf3.Varint = 0;
      Pbf3.Fixed64 = 1;
      Pbf3.Bytes = 2;
      Pbf3.Fixed32 = 5;
      var SHIFT_LEFT_322 = (1 << 16) * (1 << 16);
      var SHIFT_RIGHT_322 = 1 / SHIFT_LEFT_322;
      var TEXT_DECODER_MIN_LENGTH = 12;
      var utf8TextDecoder = typeof TextDecoder === "undefined" ? null : new TextDecoder("utf8");
      Pbf3.prototype = {
        destroy: function() {
          this.buf = null;
        },
        readFields: function(readField, result, end) {
          end = end || this.length;
          while (this.pos < end) {
            var val = this.readVarint(), tag = val >> 3, startPos = this.pos;
            this.type = val & 7;
            readField(tag, result, this);
            if (this.pos === startPos)
              this.skip(val);
          }
          return result;
        },
        readMessage: function(readField, result) {
          return this.readFields(readField, result, this.readVarint() + this.pos);
        },
        readFixed32: function() {
          var val = readUInt322(this.buf, this.pos);
          this.pos += 4;
          return val;
        },
        readSFixed32: function() {
          var val = readInt322(this.buf, this.pos);
          this.pos += 4;
          return val;
        },
        readFixed64: function() {
          var val = readUInt322(this.buf, this.pos) + readUInt322(this.buf, this.pos + 4) * SHIFT_LEFT_322;
          this.pos += 8;
          return val;
        },
        readSFixed64: function() {
          var val = readUInt322(this.buf, this.pos) + readInt322(this.buf, this.pos + 4) * SHIFT_LEFT_322;
          this.pos += 8;
          return val;
        },
        readFloat: function() {
          var val = ieee7542.read(this.buf, this.pos, true, 23, 4);
          this.pos += 4;
          return val;
        },
        readDouble: function() {
          var val = ieee7542.read(this.buf, this.pos, true, 52, 8);
          this.pos += 8;
          return val;
        },
        readVarint: function(isSigned) {
          var buf = this.buf, val, b;
          b = buf[this.pos++];
          val = b & 127;
          if (b < 128)
            return val;
          b = buf[this.pos++];
          val |= (b & 127) << 7;
          if (b < 128)
            return val;
          b = buf[this.pos++];
          val |= (b & 127) << 14;
          if (b < 128)
            return val;
          b = buf[this.pos++];
          val |= (b & 127) << 21;
          if (b < 128)
            return val;
          b = buf[this.pos];
          val |= (b & 15) << 28;
          return readVarintRemainder3(val, isSigned, this);
        },
        readVarint64: function() {
          return this.readVarint(true);
        },
        readSVarint: function() {
          var num = this.readVarint();
          return num % 2 === 1 ? (num + 1) / -2 : num / 2;
        },
        readBoolean: function() {
          return Boolean(this.readVarint());
        },
        readString: function() {
          var end = this.readVarint() + this.pos;
          var pos = this.pos;
          this.pos = end;
          if (end - pos >= TEXT_DECODER_MIN_LENGTH && utf8TextDecoder) {
            return readUtf8TextDecoder(this.buf, pos, end);
          }
          return readUtf82(this.buf, pos, end);
        },
        readBytes: function() {
          var end = this.readVarint() + this.pos, buffer = this.buf.subarray(this.pos, end);
          this.pos = end;
          return buffer;
        },
        readPackedVarint: function(arr, isSigned) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readVarint(isSigned));
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readVarint(isSigned));
          return arr;
        },
        readPackedSVarint: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readSVarint());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readSVarint());
          return arr;
        },
        readPackedBoolean: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readBoolean());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readBoolean());
          return arr;
        },
        readPackedFloat: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readFloat());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readFloat());
          return arr;
        },
        readPackedDouble: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readDouble());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readDouble());
          return arr;
        },
        readPackedFixed32: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readFixed32());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readFixed32());
          return arr;
        },
        readPackedSFixed32: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readSFixed32());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readSFixed32());
          return arr;
        },
        readPackedFixed64: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readFixed64());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readFixed64());
          return arr;
        },
        readPackedSFixed64: function(arr) {
          if (this.type !== Pbf3.Bytes)
            return arr.push(this.readSFixed64());
          var end = readPackedEnd2(this);
          arr = arr || [];
          while (this.pos < end)
            arr.push(this.readSFixed64());
          return arr;
        },
        skip: function(val) {
          var type = val & 7;
          if (type === Pbf3.Varint)
            while (this.buf[this.pos++] > 127) {
            }
          else if (type === Pbf3.Bytes)
            this.pos = this.readVarint() + this.pos;
          else if (type === Pbf3.Fixed32)
            this.pos += 4;
          else if (type === Pbf3.Fixed64)
            this.pos += 8;
          else
            throw new Error("Unimplemented type: " + type);
        },
        writeTag: function(tag, type) {
          this.writeVarint(tag << 3 | type);
        },
        realloc: function(min) {
          var length = this.length || 16;
          while (length < this.pos + min)
            length *= 2;
          if (length !== this.length) {
            var buf = new Uint8Array(length);
            buf.set(this.buf);
            this.buf = buf;
            this.length = length;
          }
        },
        finish: function() {
          this.length = this.pos;
          this.pos = 0;
          return this.buf.subarray(0, this.length);
        },
        writeFixed32: function(val) {
          this.realloc(4);
          writeInt322(this.buf, val, this.pos);
          this.pos += 4;
        },
        writeSFixed32: function(val) {
          this.realloc(4);
          writeInt322(this.buf, val, this.pos);
          this.pos += 4;
        },
        writeFixed64: function(val) {
          this.realloc(8);
          writeInt322(this.buf, val & -1, this.pos);
          writeInt322(this.buf, Math.floor(val * SHIFT_RIGHT_322), this.pos + 4);
          this.pos += 8;
        },
        writeSFixed64: function(val) {
          this.realloc(8);
          writeInt322(this.buf, val & -1, this.pos);
          writeInt322(this.buf, Math.floor(val * SHIFT_RIGHT_322), this.pos + 4);
          this.pos += 8;
        },
        writeVarint: function(val) {
          val = +val || 0;
          if (val > 268435455 || val < 0) {
            writeBigVarint2(val, this);
            return;
          }
          this.realloc(4);
          this.buf[this.pos++] = val & 127 | (val > 127 ? 128 : 0);
          if (val <= 127)
            return;
          this.buf[this.pos++] = (val >>>= 7) & 127 | (val > 127 ? 128 : 0);
          if (val <= 127)
            return;
          this.buf[this.pos++] = (val >>>= 7) & 127 | (val > 127 ? 128 : 0);
          if (val <= 127)
            return;
          this.buf[this.pos++] = val >>> 7 & 127;
        },
        writeSVarint: function(val) {
          this.writeVarint(val < 0 ? -val * 2 - 1 : val * 2);
        },
        writeBoolean: function(val) {
          this.writeVarint(Boolean(val));
        },
        writeString: function(str) {
          str = String(str);
          this.realloc(str.length * 4);
          this.pos++;
          var startPos = this.pos;
          this.pos = writeUtf82(this.buf, str, this.pos);
          var len = this.pos - startPos;
          if (len >= 128)
            makeRoomForExtraLength2(startPos, len, this);
          this.pos = startPos - 1;
          this.writeVarint(len);
          this.pos += len;
        },
        writeFloat: function(val) {
          this.realloc(4);
          ieee7542.write(this.buf, val, this.pos, true, 23, 4);
          this.pos += 4;
        },
        writeDouble: function(val) {
          this.realloc(8);
          ieee7542.write(this.buf, val, this.pos, true, 52, 8);
          this.pos += 8;
        },
        writeBytes: function(buffer) {
          var len = buffer.length;
          this.writeVarint(len);
          this.realloc(len);
          for (var i2 = 0; i2 < len; i2++)
            this.buf[this.pos++] = buffer[i2];
        },
        writeRawMessage: function(fn, obj) {
          this.pos++;
          var startPos = this.pos;
          fn(obj, this);
          var len = this.pos - startPos;
          if (len >= 128)
            makeRoomForExtraLength2(startPos, len, this);
          this.pos = startPos - 1;
          this.writeVarint(len);
          this.pos += len;
        },
        writeMessage: function(tag, fn, obj) {
          this.writeTag(tag, Pbf3.Bytes);
          this.writeRawMessage(fn, obj);
        },
        writePackedVarint: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedVarint2, arr);
        },
        writePackedSVarint: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedSVarint2, arr);
        },
        writePackedBoolean: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedBoolean2, arr);
        },
        writePackedFloat: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedFloat2, arr);
        },
        writePackedDouble: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedDouble2, arr);
        },
        writePackedFixed32: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedFixed322, arr);
        },
        writePackedSFixed32: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedSFixed322, arr);
        },
        writePackedFixed64: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedFixed642, arr);
        },
        writePackedSFixed64: function(tag, arr) {
          if (arr.length)
            this.writeMessage(tag, writePackedSFixed642, arr);
        },
        writeBytesField: function(tag, buffer) {
          this.writeTag(tag, Pbf3.Bytes);
          this.writeBytes(buffer);
        },
        writeFixed32Field: function(tag, val) {
          this.writeTag(tag, Pbf3.Fixed32);
          this.writeFixed32(val);
        },
        writeSFixed32Field: function(tag, val) {
          this.writeTag(tag, Pbf3.Fixed32);
          this.writeSFixed32(val);
        },
        writeFixed64Field: function(tag, val) {
          this.writeTag(tag, Pbf3.Fixed64);
          this.writeFixed64(val);
        },
        writeSFixed64Field: function(tag, val) {
          this.writeTag(tag, Pbf3.Fixed64);
          this.writeSFixed64(val);
        },
        writeVarintField: function(tag, val) {
          this.writeTag(tag, Pbf3.Varint);
          this.writeVarint(val);
        },
        writeSVarintField: function(tag, val) {
          this.writeTag(tag, Pbf3.Varint);
          this.writeSVarint(val);
        },
        writeStringField: function(tag, str) {
          this.writeTag(tag, Pbf3.Bytes);
          this.writeString(str);
        },
        writeFloatField: function(tag, val) {
          this.writeTag(tag, Pbf3.Fixed32);
          this.writeFloat(val);
        },
        writeDoubleField: function(tag, val) {
          this.writeTag(tag, Pbf3.Fixed64);
          this.writeDouble(val);
        },
        writeBooleanField: function(tag, val) {
          this.writeVarintField(tag, Boolean(val));
        }
      };
      function readVarintRemainder3(l, s, p) {
        var buf = p.buf, h, b;
        b = buf[p.pos++];
        h = (b & 112) >> 4;
        if (b < 128)
          return toNum3(l, h, s);
        b = buf[p.pos++];
        h |= (b & 127) << 3;
        if (b < 128)
          return toNum3(l, h, s);
        b = buf[p.pos++];
        h |= (b & 127) << 10;
        if (b < 128)
          return toNum3(l, h, s);
        b = buf[p.pos++];
        h |= (b & 127) << 17;
        if (b < 128)
          return toNum3(l, h, s);
        b = buf[p.pos++];
        h |= (b & 127) << 24;
        if (b < 128)
          return toNum3(l, h, s);
        b = buf[p.pos++];
        h |= (b & 1) << 31;
        if (b < 128)
          return toNum3(l, h, s);
        throw new Error("Expected varint not more than 10 bytes");
      }
      function readPackedEnd2(pbf) {
        return pbf.type === Pbf3.Bytes ? pbf.readVarint() + pbf.pos : pbf.pos + 1;
      }
      function toNum3(low, high, isSigned) {
        if (isSigned) {
          return high * 4294967296 + (low >>> 0);
        }
        return (high >>> 0) * 4294967296 + (low >>> 0);
      }
      function writeBigVarint2(val, pbf) {
        var low, high;
        if (val >= 0) {
          low = val % 4294967296 | 0;
          high = val / 4294967296 | 0;
        } else {
          low = ~(-val % 4294967296);
          high = ~(-val / 4294967296);
          if (low ^ 4294967295) {
            low = low + 1 | 0;
          } else {
            low = 0;
            high = high + 1 | 0;
          }
        }
        if (val >= 18446744073709552e3 || val < -18446744073709552e3) {
          throw new Error("Given varint doesn't fit into 10 bytes");
        }
        pbf.realloc(10);
        writeBigVarintLow2(low, high, pbf);
        writeBigVarintHigh2(high, pbf);
      }
      function writeBigVarintLow2(low, high, pbf) {
        pbf.buf[pbf.pos++] = low & 127 | 128;
        low >>>= 7;
        pbf.buf[pbf.pos++] = low & 127 | 128;
        low >>>= 7;
        pbf.buf[pbf.pos++] = low & 127 | 128;
        low >>>= 7;
        pbf.buf[pbf.pos++] = low & 127 | 128;
        low >>>= 7;
        pbf.buf[pbf.pos] = low & 127;
      }
      function writeBigVarintHigh2(high, pbf) {
        var lsb = (high & 7) << 4;
        pbf.buf[pbf.pos++] |= lsb | ((high >>>= 3) ? 128 : 0);
        if (!high)
          return;
        pbf.buf[pbf.pos++] = high & 127 | ((high >>>= 7) ? 128 : 0);
        if (!high)
          return;
        pbf.buf[pbf.pos++] = high & 127 | ((high >>>= 7) ? 128 : 0);
        if (!high)
          return;
        pbf.buf[pbf.pos++] = high & 127 | ((high >>>= 7) ? 128 : 0);
        if (!high)
          return;
        pbf.buf[pbf.pos++] = high & 127 | ((high >>>= 7) ? 128 : 0);
        if (!high)
          return;
        pbf.buf[pbf.pos++] = high & 127;
      }
      function makeRoomForExtraLength2(startPos, len, pbf) {
        var extraLen = len <= 16383 ? 1 : len <= 2097151 ? 2 : len <= 268435455 ? 3 : Math.floor(Math.log(len) / (Math.LN2 * 7));
        pbf.realloc(extraLen);
        for (var i2 = pbf.pos - 1; i2 >= startPos; i2--)
          pbf.buf[i2 + extraLen] = pbf.buf[i2];
      }
      function writePackedVarint2(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeVarint(arr[i2]);
      }
      function writePackedSVarint2(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeSVarint(arr[i2]);
      }
      function writePackedFloat2(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeFloat(arr[i2]);
      }
      function writePackedDouble2(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeDouble(arr[i2]);
      }
      function writePackedBoolean2(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeBoolean(arr[i2]);
      }
      function writePackedFixed322(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeFixed32(arr[i2]);
      }
      function writePackedSFixed322(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeSFixed32(arr[i2]);
      }
      function writePackedFixed642(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeFixed64(arr[i2]);
      }
      function writePackedSFixed642(arr, pbf) {
        for (var i2 = 0; i2 < arr.length; i2++)
          pbf.writeSFixed64(arr[i2]);
      }
      function readUInt322(buf, pos) {
        return (buf[pos] | buf[pos + 1] << 8 | buf[pos + 2] << 16) + buf[pos + 3] * 16777216;
      }
      function writeInt322(buf, val, pos) {
        buf[pos] = val;
        buf[pos + 1] = val >>> 8;
        buf[pos + 2] = val >>> 16;
        buf[pos + 3] = val >>> 24;
      }
      function readInt322(buf, pos) {
        return (buf[pos] | buf[pos + 1] << 8 | buf[pos + 2] << 16) + (buf[pos + 3] << 24);
      }
      function readUtf82(buf, pos, end) {
        var str = "";
        var i2 = pos;
        while (i2 < end) {
          var b0 = buf[i2];
          var c = null;
          var bytesPerSequence = b0 > 239 ? 4 : b0 > 223 ? 3 : b0 > 191 ? 2 : 1;
          if (i2 + bytesPerSequence > end)
            break;
          var b1, b2, b3;
          if (bytesPerSequence === 1) {
            if (b0 < 128) {
              c = b0;
            }
          } else if (bytesPerSequence === 2) {
            b1 = buf[i2 + 1];
            if ((b1 & 192) === 128) {
              c = (b0 & 31) << 6 | b1 & 63;
              if (c <= 127) {
                c = null;
              }
            }
          } else if (bytesPerSequence === 3) {
            b1 = buf[i2 + 1];
            b2 = buf[i2 + 2];
            if ((b1 & 192) === 128 && (b2 & 192) === 128) {
              c = (b0 & 15) << 12 | (b1 & 63) << 6 | b2 & 63;
              if (c <= 2047 || c >= 55296 && c <= 57343) {
                c = null;
              }
            }
          } else if (bytesPerSequence === 4) {
            b1 = buf[i2 + 1];
            b2 = buf[i2 + 2];
            b3 = buf[i2 + 3];
            if ((b1 & 192) === 128 && (b2 & 192) === 128 && (b3 & 192) === 128) {
              c = (b0 & 15) << 18 | (b1 & 63) << 12 | (b2 & 63) << 6 | b3 & 63;
              if (c <= 65535 || c >= 1114112) {
                c = null;
              }
            }
          }
          if (c === null) {
            c = 65533;
            bytesPerSequence = 1;
          } else if (c > 65535) {
            c -= 65536;
            str += String.fromCharCode(c >>> 10 & 1023 | 55296);
            c = 56320 | c & 1023;
          }
          str += String.fromCharCode(c);
          i2 += bytesPerSequence;
        }
        return str;
      }
      function readUtf8TextDecoder(buf, pos, end) {
        return utf8TextDecoder.decode(buf.subarray(pos, end));
      }
      function writeUtf82(buf, str, pos) {
        for (var i2 = 0, c, lead; i2 < str.length; i2++) {
          c = str.charCodeAt(i2);
          if (c > 55295 && c < 57344) {
            if (lead) {
              if (c < 56320) {
                buf[pos++] = 239;
                buf[pos++] = 191;
                buf[pos++] = 189;
                lead = c;
                continue;
              } else {
                c = lead - 55296 << 10 | c - 56320 | 65536;
                lead = null;
              }
            } else {
              if (c > 56319 || i2 + 1 === str.length) {
                buf[pos++] = 239;
                buf[pos++] = 191;
                buf[pos++] = 189;
              } else {
                lead = c;
              }
              continue;
            }
          } else if (lead) {
            buf[pos++] = 239;
            buf[pos++] = 191;
            buf[pos++] = 189;
            lead = null;
          }
          if (c < 128) {
            buf[pos++] = c;
          } else {
            if (c < 2048) {
              buf[pos++] = c >> 6 | 192;
            } else {
              if (c < 65536) {
                buf[pos++] = c >> 12 | 224;
              } else {
                buf[pos++] = c >> 18 | 240;
                buf[pos++] = c >> 12 & 63 | 128;
              }
              buf[pos++] = c >> 6 & 63 | 128;
            }
            buf[pos++] = c & 63 | 128;
          }
        }
        return pos;
      }
    }
  });

  // node_modules/@mapbox/point-geometry/index.js
  var require_point_geometry = __commonJS({
    "node_modules/@mapbox/point-geometry/index.js"(exports, module) {
      "use strict";
      module.exports = Point2;
      function Point2(x2, y) {
        this.x = x2;
        this.y = y;
      }
      Point2.prototype = {
        clone: function() {
          return new Point2(this.x, this.y);
        },
        add: function(p) {
          return this.clone()._add(p);
        },
        sub: function(p) {
          return this.clone()._sub(p);
        },
        multByPoint: function(p) {
          return this.clone()._multByPoint(p);
        },
        divByPoint: function(p) {
          return this.clone()._divByPoint(p);
        },
        mult: function(k) {
          return this.clone()._mult(k);
        },
        div: function(k) {
          return this.clone()._div(k);
        },
        rotate: function(a) {
          return this.clone()._rotate(a);
        },
        rotateAround: function(a, p) {
          return this.clone()._rotateAround(a, p);
        },
        matMult: function(m) {
          return this.clone()._matMult(m);
        },
        unit: function() {
          return this.clone()._unit();
        },
        perp: function() {
          return this.clone()._perp();
        },
        round: function() {
          return this.clone()._round();
        },
        mag: function() {
          return Math.sqrt(this.x * this.x + this.y * this.y);
        },
        equals: function(other) {
          return this.x === other.x && this.y === other.y;
        },
        dist: function(p) {
          return Math.sqrt(this.distSqr(p));
        },
        distSqr: function(p) {
          var dx = p.x - this.x, dy = p.y - this.y;
          return dx * dx + dy * dy;
        },
        angle: function() {
          return Math.atan2(this.y, this.x);
        },
        angleTo: function(b) {
          return Math.atan2(this.y - b.y, this.x - b.x);
        },
        angleWith: function(b) {
          return this.angleWithSep(b.x, b.y);
        },
        angleWithSep: function(x2, y) {
          return Math.atan2(
            this.x * y - this.y * x2,
            this.x * x2 + this.y * y
          );
        },
        _matMult: function(m) {
          var x2 = m[0] * this.x + m[1] * this.y, y = m[2] * this.x + m[3] * this.y;
          this.x = x2;
          this.y = y;
          return this;
        },
        _add: function(p) {
          this.x += p.x;
          this.y += p.y;
          return this;
        },
        _sub: function(p) {
          this.x -= p.x;
          this.y -= p.y;
          return this;
        },
        _mult: function(k) {
          this.x *= k;
          this.y *= k;
          return this;
        },
        _div: function(k) {
          this.x /= k;
          this.y /= k;
          return this;
        },
        _multByPoint: function(p) {
          this.x *= p.x;
          this.y *= p.y;
          return this;
        },
        _divByPoint: function(p) {
          this.x /= p.x;
          this.y /= p.y;
          return this;
        },
        _unit: function() {
          this._div(this.mag());
          return this;
        },
        _perp: function() {
          var y = this.y;
          this.y = this.x;
          this.x = -y;
          return this;
        },
        _rotate: function(angle) {
          var cos = Math.cos(angle), sin = Math.sin(angle), x2 = cos * this.x - sin * this.y, y = sin * this.x + cos * this.y;
          this.x = x2;
          this.y = y;
          return this;
        },
        _rotateAround: function(angle, p) {
          var cos = Math.cos(angle), sin = Math.sin(angle), x2 = p.x + cos * (this.x - p.x) - sin * (this.y - p.y), y = p.y + sin * (this.x - p.x) + cos * (this.y - p.y);
          this.x = x2;
          this.y = y;
          return this;
        },
        _round: function() {
          this.x = Math.round(this.x);
          this.y = Math.round(this.y);
          return this;
        }
      };
      Point2.convert = function(a) {
        if (a instanceof Point2) {
          return a;
        }
        if (Array.isArray(a)) {
          return new Point2(a[0], a[1]);
        }
        return a;
      };
    }
  });

  // node_modules/@mapbox/vector-tile/lib/vectortilefeature.js
  var require_vectortilefeature = __commonJS({
    "node_modules/@mapbox/vector-tile/lib/vectortilefeature.js"(exports, module) {
      "use strict";
      var Point2 = require_point_geometry();
      module.exports = VectorTileFeature;
      function VectorTileFeature(pbf, end, extent, keys, values) {
        this.properties = {};
        this.extent = extent;
        this.type = 0;
        this._pbf = pbf;
        this._geometry = -1;
        this._keys = keys;
        this._values = values;
        pbf.readFields(readFeature2, this, end);
      }
      function readFeature2(tag, feature, pbf) {
        if (tag == 1)
          feature.id = pbf.readVarint();
        else if (tag == 2)
          readTag2(pbf, feature);
        else if (tag == 3)
          feature.type = pbf.readVarint();
        else if (tag == 4)
          feature._geometry = pbf.pos;
      }
      function readTag2(pbf, feature) {
        var end = pbf.readVarint() + pbf.pos;
        while (pbf.pos < end) {
          var key = feature._keys[pbf.readVarint()], value = feature._values[pbf.readVarint()];
          feature.properties[key] = value;
        }
      }
      VectorTileFeature.types = ["Unknown", "Point", "LineString", "Polygon"];
      VectorTileFeature.prototype.loadGeometry = function() {
        var pbf = this._pbf;
        pbf.pos = this._geometry;
        var end = pbf.readVarint() + pbf.pos, cmd = 1, length = 0, x2 = 0, y = 0, lines = [], line;
        while (pbf.pos < end) {
          if (length <= 0) {
            var cmdLen = pbf.readVarint();
            cmd = cmdLen & 7;
            length = cmdLen >> 3;
          }
          length--;
          if (cmd === 1 || cmd === 2) {
            x2 += pbf.readSVarint();
            y += pbf.readSVarint();
            if (cmd === 1) {
              if (line)
                lines.push(line);
              line = [];
            }
            line.push(new Point2(x2, y));
          } else if (cmd === 7) {
            if (line) {
              line.push(line[0].clone());
            }
          } else {
            throw new Error("unknown command " + cmd);
          }
        }
        if (line)
          lines.push(line);
        return lines;
      };
      VectorTileFeature.prototype.bbox = function() {
        var pbf = this._pbf;
        pbf.pos = this._geometry;
        var end = pbf.readVarint() + pbf.pos, cmd = 1, length = 0, x2 = 0, y = 0, x1 = Infinity, x22 = -Infinity, y1 = Infinity, y2 = -Infinity;
        while (pbf.pos < end) {
          if (length <= 0) {
            var cmdLen = pbf.readVarint();
            cmd = cmdLen & 7;
            length = cmdLen >> 3;
          }
          length--;
          if (cmd === 1 || cmd === 2) {
            x2 += pbf.readSVarint();
            y += pbf.readSVarint();
            if (x2 < x1)
              x1 = x2;
            if (x2 > x22)
              x22 = x2;
            if (y < y1)
              y1 = y;
            if (y > y2)
              y2 = y;
          } else if (cmd !== 7) {
            throw new Error("unknown command " + cmd);
          }
        }
        return [x1, y1, x22, y2];
      };
      VectorTileFeature.prototype.toGeoJSON = function(x2, y, z) {
        var size = this.extent * Math.pow(2, z), x0 = this.extent * x2, y0 = this.extent * y, coords = this.loadGeometry(), type = VectorTileFeature.types[this.type], i2, j;
        function project(line) {
          for (var j2 = 0; j2 < line.length; j2++) {
            var p = line[j2], y2 = 180 - (p.y + y0) * 360 / size;
            line[j2] = [
              (p.x + x0) * 360 / size - 180,
              360 / Math.PI * Math.atan(Math.exp(y2 * Math.PI / 180)) - 90
            ];
          }
        }
        switch (this.type) {
          case 1:
            var points = [];
            for (i2 = 0; i2 < coords.length; i2++) {
              points[i2] = coords[i2][0];
            }
            coords = points;
            project(coords);
            break;
          case 2:
            for (i2 = 0; i2 < coords.length; i2++) {
              project(coords[i2]);
            }
            break;
          case 3:
            coords = classifyRings2(coords);
            for (i2 = 0; i2 < coords.length; i2++) {
              for (j = 0; j < coords[i2].length; j++) {
                project(coords[i2][j]);
              }
            }
            break;
        }
        if (coords.length === 1) {
          coords = coords[0];
        } else {
          type = "Multi" + type;
        }
        var result = {
          type: "Feature",
          geometry: {
            type,
            coordinates: coords
          },
          properties: this.properties
        };
        if ("id" in this) {
          result.id = this.id;
        }
        return result;
      };
      function classifyRings2(rings) {
        var len = rings.length;
        if (len <= 1)
          return [rings];
        var polygons = [], polygon, ccw;
        for (var i2 = 0; i2 < len; i2++) {
          var area = signedArea2(rings[i2]);
          if (area === 0)
            continue;
          if (ccw === void 0)
            ccw = area < 0;
          if (ccw === area < 0) {
            if (polygon)
              polygons.push(polygon);
            polygon = [rings[i2]];
          } else {
            polygon.push(rings[i2]);
          }
        }
        if (polygon)
          polygons.push(polygon);
        return polygons;
      }
      function signedArea2(ring) {
        var sum = 0;
        for (var i2 = 0, len = ring.length, j = len - 1, p1, p2; i2 < len; j = i2++) {
          p1 = ring[i2];
          p2 = ring[j];
          sum += (p2.x - p1.x) * (p1.y + p2.y);
        }
        return sum;
      }
    }
  });

  // node_modules/@mapbox/vector-tile/lib/vectortilelayer.js
  var require_vectortilelayer = __commonJS({
    "node_modules/@mapbox/vector-tile/lib/vectortilelayer.js"(exports, module) {
      "use strict";
      var VectorTileFeature = require_vectortilefeature();
      module.exports = VectorTileLayer;
      function VectorTileLayer(pbf, end) {
        this.version = 1;
        this.name = null;
        this.extent = 4096;
        this.length = 0;
        this._pbf = pbf;
        this._keys = [];
        this._values = [];
        this._features = [];
        pbf.readFields(readLayer2, this, end);
        this.length = this._features.length;
      }
      function readLayer2(tag, layer, pbf) {
        if (tag === 15)
          layer.version = pbf.readVarint();
        else if (tag === 1)
          layer.name = pbf.readString();
        else if (tag === 5)
          layer.extent = pbf.readVarint();
        else if (tag === 2)
          layer._features.push(pbf.pos);
        else if (tag === 3)
          layer._keys.push(pbf.readString());
        else if (tag === 4)
          layer._values.push(readValueMessage2(pbf));
      }
      function readValueMessage2(pbf) {
        var value = null, end = pbf.readVarint() + pbf.pos;
        while (pbf.pos < end) {
          var tag = pbf.readVarint() >> 3;
          value = tag === 1 ? pbf.readString() : tag === 2 ? pbf.readFloat() : tag === 3 ? pbf.readDouble() : tag === 4 ? pbf.readVarint64() : tag === 5 ? pbf.readVarint() : tag === 6 ? pbf.readSVarint() : tag === 7 ? pbf.readBoolean() : null;
        }
        return value;
      }
      VectorTileLayer.prototype.feature = function(i2) {
        if (i2 < 0 || i2 >= this._features.length)
          throw new Error("feature index out of bounds");
        this._pbf.pos = this._features[i2];
        var end = this._pbf.readVarint() + this._pbf.pos;
        return new VectorTileFeature(this._pbf, end, this.extent, this._keys, this._values);
      };
    }
  });

  // node_modules/@mapbox/vector-tile/lib/vectortile.js
  var require_vectortile = __commonJS({
    "node_modules/@mapbox/vector-tile/lib/vectortile.js"(exports, module) {
      "use strict";
      var VectorTileLayer = require_vectortilelayer();
      module.exports = VectorTile3;
      function VectorTile3(pbf, end) {
        this.layers = pbf.readFields(readTile2, {}, end);
      }
      function readTile2(tag, layers, pbf) {
        if (tag === 3) {
          var layer = new VectorTileLayer(pbf, pbf.readVarint() + pbf.pos);
          if (layer.length)
            layers[layer.name] = layer;
        }
      }
    }
  });

  // node_modules/@mapbox/vector-tile/index.js
  var require_vector_tile = __commonJS({
    "node_modules/@mapbox/vector-tile/index.js"(exports, module) {
      module.exports.VectorTile = require_vectortile();
      module.exports.VectorTileFeature = require_vectortilefeature();
      module.exports.VectorTileLayer = require_vectortilelayer();
    }
  });

  // src/index.js
  var import_pbf = __toESM(require_pbf());
  var import_vector_tile = __toESM(require_vector_tile());

  // node_modules/leaflet.vectorgrid/dist/Leaflet.VectorGrid.bundled.min.js
  function __$strToBlobUri(t, e, r) {
    try {
      return window.URL.createObjectURL(new Blob([Uint8Array.from(t.split("").map(function(t2) {
        return t2.charCodeAt(0);
      }))], { type: e }));
    } catch (i2) {
      return "data:" + e + (r ? ";base64," : ",") + t;
    }
  }
  function Pbf(t) {
    this.buf = ArrayBuffer.isView && ArrayBuffer.isView(t) ? t : new Uint8Array(t || 0), this.pos = 0, this.type = 0, this.length = this.buf.length;
  }
  function readVarintRemainder(t, e, r) {
    var i2, n, o = r.buf;
    if (n = o[r.pos++], i2 = (112 & n) >> 4, n < 128)
      return toNum(t, i2, e);
    if (n = o[r.pos++], i2 |= (127 & n) << 3, n < 128)
      return toNum(t, i2, e);
    if (n = o[r.pos++], i2 |= (127 & n) << 10, n < 128)
      return toNum(t, i2, e);
    if (n = o[r.pos++], i2 |= (127 & n) << 17, n < 128)
      return toNum(t, i2, e);
    if (n = o[r.pos++], i2 |= (127 & n) << 24, n < 128)
      return toNum(t, i2, e);
    if (n = o[r.pos++], i2 |= (1 & n) << 31, n < 128)
      return toNum(t, i2, e);
    throw new Error("Expected varint not more than 10 bytes");
  }
  function readPackedEnd(t) {
    return t.type === Pbf.Bytes ? t.readVarint() + t.pos : t.pos + 1;
  }
  function toNum(t, e, r) {
    return r ? 4294967296 * e + (t >>> 0) : 4294967296 * (e >>> 0) + (t >>> 0);
  }
  function writeBigVarint(t, e) {
    var r, i2;
    if (t >= 0 ? (r = t % 4294967296 | 0, i2 = t / 4294967296 | 0) : (r = ~(-t % 4294967296), i2 = ~(-t / 4294967296), 4294967295 ^ r ? r = r + 1 | 0 : (r = 0, i2 = i2 + 1 | 0)), t >= 18446744073709552e3 || t < -18446744073709552e3)
      throw new Error("Given varint doesn't fit into 10 bytes");
    e.realloc(10), writeBigVarintLow(r, i2, e), writeBigVarintHigh(i2, e);
  }
  function writeBigVarintLow(t, e, r) {
    r.buf[r.pos++] = 127 & t | 128, t >>>= 7, r.buf[r.pos++] = 127 & t | 128, t >>>= 7, r.buf[r.pos++] = 127 & t | 128, t >>>= 7, r.buf[r.pos++] = 127 & t | 128, t >>>= 7, r.buf[r.pos] = 127 & t;
  }
  function writeBigVarintHigh(t, e) {
    var r = (7 & t) << 4;
    e.buf[e.pos++] |= r | ((t >>>= 3) ? 128 : 0), t && (e.buf[e.pos++] = 127 & t | ((t >>>= 7) ? 128 : 0), t && (e.buf[e.pos++] = 127 & t | ((t >>>= 7) ? 128 : 0), t && (e.buf[e.pos++] = 127 & t | ((t >>>= 7) ? 128 : 0), t && (e.buf[e.pos++] = 127 & t | ((t >>>= 7) ? 128 : 0), t && (e.buf[e.pos++] = 127 & t)))));
  }
  function makeRoomForExtraLength(t, e, r) {
    var i2 = e <= 16383 ? 1 : e <= 2097151 ? 2 : e <= 268435455 ? 3 : Math.ceil(Math.log(e) / (7 * Math.LN2));
    r.realloc(i2);
    for (var n = r.pos - 1; n >= t; n--)
      r.buf[n + i2] = r.buf[n];
  }
  function writePackedVarint(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeVarint(t[r]);
  }
  function writePackedSVarint(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeSVarint(t[r]);
  }
  function writePackedFloat(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeFloat(t[r]);
  }
  function writePackedDouble(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeDouble(t[r]);
  }
  function writePackedBoolean(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeBoolean(t[r]);
  }
  function writePackedFixed32(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeFixed32(t[r]);
  }
  function writePackedSFixed32(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeSFixed32(t[r]);
  }
  function writePackedFixed64(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeFixed64(t[r]);
  }
  function writePackedSFixed64(t, e) {
    for (var r = 0; r < t.length; r++)
      e.writeSFixed64(t[r]);
  }
  function readUInt32(t, e) {
    return (t[e] | t[e + 1] << 8 | t[e + 2] << 16) + 16777216 * t[e + 3];
  }
  function writeInt32(t, e, r) {
    t[r] = e, t[r + 1] = e >>> 8, t[r + 2] = e >>> 16, t[r + 3] = e >>> 24;
  }
  function readInt32(t, e) {
    return (t[e] | t[e + 1] << 8 | t[e + 2] << 16) + (t[e + 3] << 24);
  }
  function readUtf8(t, e, r) {
    for (var i2 = "", n = e; n < r; ) {
      var o = t[n], s = null, a = o > 239 ? 4 : o > 223 ? 3 : o > 191 ? 2 : 1;
      if (n + a > r)
        break;
      var u, h, l;
      1 === a ? o < 128 && (s = o) : 2 === a ? 128 == (192 & (u = t[n + 1])) && (s = (31 & o) << 6 | 63 & u) <= 127 && (s = null) : 3 === a ? (u = t[n + 1], h = t[n + 2], 128 == (192 & u) && 128 == (192 & h) && ((s = (15 & o) << 12 | (63 & u) << 6 | 63 & h) <= 2047 || s >= 55296 && s <= 57343) && (s = null)) : 4 === a && (u = t[n + 1], h = t[n + 2], l = t[n + 3], 128 == (192 & u) && 128 == (192 & h) && 128 == (192 & l) && ((s = (15 & o) << 18 | (63 & u) << 12 | (63 & h) << 6 | 63 & l) <= 65535 || s >= 1114112) && (s = null)), null === s ? (s = 65533, a = 1) : s > 65535 && (s -= 65536, i2 += String.fromCharCode(s >>> 10 & 1023 | 55296), s = 56320 | 1023 & s), i2 += String.fromCharCode(s), n += a;
    }
    return i2;
  }
  function writeUtf8(t, e, r) {
    for (var i2, n, o = 0; o < e.length; o++) {
      if ((i2 = e.charCodeAt(o)) > 55295 && i2 < 57344) {
        if (!n) {
          i2 > 56319 || o + 1 === e.length ? (t[r++] = 239, t[r++] = 191, t[r++] = 189) : n = i2;
          continue;
        }
        if (i2 < 56320) {
          t[r++] = 239, t[r++] = 191, t[r++] = 189, n = i2;
          continue;
        }
        i2 = n - 55296 << 10 | i2 - 56320 | 65536, n = null;
      } else
        n && (t[r++] = 239, t[r++] = 191, t[r++] = 189, n = null);
      i2 < 128 ? t[r++] = i2 : (i2 < 2048 ? t[r++] = i2 >> 6 | 192 : (i2 < 65536 ? t[r++] = i2 >> 12 | 224 : (t[r++] = i2 >> 18 | 240, t[r++] = i2 >> 12 & 63 | 128), t[r++] = i2 >> 6 & 63 | 128), t[r++] = 63 & i2 | 128);
    }
    return r;
  }
  function Point$1(t, e) {
    this.x = t, this.y = e;
  }
  function VectorTileFeature$2(t, e, r, i2, n) {
    this.properties = {}, this.extent = r, this.type = 0, this._pbf = t, this._geometry = -1, this._keys = i2, this._values = n, t.readFields(readFeature, this, e);
  }
  function readFeature(t, e, r) {
    1 == t ? e.id = r.readVarint() : 2 == t ? readTag(r, e) : 3 == t ? e.type = r.readVarint() : 4 == t && (e._geometry = r.pos);
  }
  function readTag(t, e) {
    for (var r = t.readVarint() + t.pos; t.pos < r; ) {
      var i2 = e._keys[t.readVarint()], n = e._values[t.readVarint()];
      e.properties[i2] = n;
    }
  }
  function classifyRings(t) {
    var e = t.length;
    if (e <= 1)
      return [t];
    for (var r, i2, n = [], o = 0; o < e; o++) {
      var s = signedArea(t[o]);
      0 !== s && (void 0 === i2 && (i2 = s < 0), i2 === s < 0 ? (r && n.push(r), r = [t[o]]) : r.push(t[o]));
    }
    return r && n.push(r), n;
  }
  function signedArea(t) {
    for (var e, r, i2 = 0, n = 0, o = t.length, s = o - 1; n < o; s = n++)
      e = t[n], r = t[s], i2 += (r.x - e.x) * (e.y + r.y);
    return i2;
  }
  function VectorTileLayer$2(t, e) {
    this.version = 1, this.name = null, this.extent = 4096, this.length = 0, this._pbf = t, this._keys = [], this._values = [], this._features = [], t.readFields(readLayer, this, e), this.length = this._features.length;
  }
  function readLayer(t, e, r) {
    15 === t ? e.version = r.readVarint() : 1 === t ? e.name = r.readString() : 5 === t ? e.extent = r.readVarint() : 2 === t ? e._features.push(r.pos) : 3 === t ? e._keys.push(r.readString()) : 4 === t && e._values.push(readValueMessage(r));
  }
  function readValueMessage(t) {
    for (var e = null, r = t.readVarint() + t.pos; t.pos < r; ) {
      var i2 = t.readVarint() >> 3;
      e = 1 === i2 ? t.readString() : 2 === i2 ? t.readFloat() : 3 === i2 ? t.readDouble() : 4 === i2 ? t.readVarint64() : 5 === i2 ? t.readVarint() : 6 === i2 ? t.readSVarint() : 7 === i2 ? t.readBoolean() : null;
    }
    return e;
  }
  function VectorTile$1(t, e) {
    this.layers = t.readFields(readTile, {}, e);
  }
  function readTile(t, e, r) {
    if (3 === t) {
      var i2 = new VectorTileLayer$1(r, r.readVarint() + r.pos);
      i2.length && (e[i2.name] = i2);
    }
  }
  !function(t) {
    function e(t2) {
      if ("string" != typeof t2 && (t2 = String(t2)), /[^a-z0-9\-#$%&'*+.\^_`|~]/i.test(t2))
        throw new TypeError("Invalid character in header field name");
      return t2.toLowerCase();
    }
    function r(t2) {
      return "string" != typeof t2 && (t2 = String(t2)), t2;
    }
    function i2(t2) {
      var e2 = { next: function() {
        var e3 = t2.shift();
        return { done: void 0 === e3, value: e3 };
      } };
      return v.iterable && (e2[Symbol.iterator] = function() {
        return e2;
      }), e2;
    }
    function n(t2) {
      this.map = {}, t2 instanceof n ? t2.forEach(function(t3, e2) {
        this.append(e2, t3);
      }, this) : Array.isArray(t2) ? t2.forEach(function(t3) {
        this.append(t3[0], t3[1]);
      }, this) : t2 && Object.getOwnPropertyNames(t2).forEach(function(e2) {
        this.append(e2, t2[e2]);
      }, this);
    }
    function o(t2) {
      if (t2.bodyUsed)
        return Promise.reject(new TypeError("Already read"));
      t2.bodyUsed = true;
    }
    function s(t2) {
      return new Promise(function(e2, r2) {
        t2.onload = function() {
          e2(t2.result);
        }, t2.onerror = function() {
          r2(t2.error);
        };
      });
    }
    function a(t2) {
      var e2 = new FileReader(), r2 = s(e2);
      return e2.readAsArrayBuffer(t2), r2;
    }
    function u(t2) {
      var e2 = new FileReader(), r2 = s(e2);
      return e2.readAsText(t2), r2;
    }
    function h(t2) {
      for (var e2 = new Uint8Array(t2), r2 = new Array(e2.length), i3 = 0; i3 < e2.length; i3++)
        r2[i3] = String.fromCharCode(e2[i3]);
      return r2.join("");
    }
    function l(t2) {
      if (t2.slice)
        return t2.slice(0);
      var e2 = new Uint8Array(t2.byteLength);
      return e2.set(new Uint8Array(t2)), e2.buffer;
    }
    function c() {
      return this.bodyUsed = false, this._initBody = function(t2) {
        if (this._bodyInit = t2, t2)
          if ("string" == typeof t2)
            this._bodyText = t2;
          else if (v.blob && Blob.prototype.isPrototypeOf(t2))
            this._bodyBlob = t2;
          else if (v.formData && FormData.prototype.isPrototypeOf(t2))
            this._bodyFormData = t2;
          else if (v.searchParams && URLSearchParams.prototype.isPrototypeOf(t2))
            this._bodyText = t2.toString();
          else if (v.arrayBuffer && v.blob && b(t2))
            this._bodyArrayBuffer = l(t2.buffer), this._bodyInit = new Blob([this._bodyArrayBuffer]);
          else {
            if (!v.arrayBuffer || !ArrayBuffer.prototype.isPrototypeOf(t2) && !w(t2))
              throw new Error("unsupported BodyInit type");
            this._bodyArrayBuffer = l(t2);
          }
        else
          this._bodyText = "";
        this.headers.get("content-type") || ("string" == typeof t2 ? this.headers.set("content-type", "text/plain;charset=UTF-8") : this._bodyBlob && this._bodyBlob.type ? this.headers.set("content-type", this._bodyBlob.type) : v.searchParams && URLSearchParams.prototype.isPrototypeOf(t2) && this.headers.set("content-type", "application/x-www-form-urlencoded;charset=UTF-8"));
      }, v.blob && (this.blob = function() {
        var t2 = o(this);
        if (t2)
          return t2;
        if (this._bodyBlob)
          return Promise.resolve(this._bodyBlob);
        if (this._bodyArrayBuffer)
          return Promise.resolve(new Blob([this._bodyArrayBuffer]));
        if (this._bodyFormData)
          throw new Error("could not read FormData body as blob");
        return Promise.resolve(new Blob([this._bodyText]));
      }, this.arrayBuffer = function() {
        return this._bodyArrayBuffer ? o(this) || Promise.resolve(this._bodyArrayBuffer) : this.blob().then(a);
      }), this.text = function() {
        var t2 = o(this);
        if (t2)
          return t2;
        if (this._bodyBlob)
          return u(this._bodyBlob);
        if (this._bodyArrayBuffer)
          return Promise.resolve(h(this._bodyArrayBuffer));
        if (this._bodyFormData)
          throw new Error("could not read FormData body as text");
        return Promise.resolve(this._bodyText);
      }, v.formData && (this.formData = function() {
        return this.text().then(d);
      }), this.json = function() {
        return this.text().then(JSON.parse);
      }, this;
    }
    function f(t2) {
      var e2 = t2.toUpperCase();
      return _.indexOf(e2) > -1 ? e2 : t2;
    }
    function p(t2, e2) {
      e2 = e2 || {};
      var r2 = e2.body;
      if (t2 instanceof p) {
        if (t2.bodyUsed)
          throw new TypeError("Already read");
        this.url = t2.url, this.credentials = t2.credentials, e2.headers || (this.headers = new n(t2.headers)), this.method = t2.method, this.mode = t2.mode, r2 || null == t2._bodyInit || (r2 = t2._bodyInit, t2.bodyUsed = true);
      } else
        this.url = String(t2);
      if (this.credentials = e2.credentials || this.credentials || "omit", !e2.headers && this.headers || (this.headers = new n(e2.headers)), this.method = f(e2.method || this.method || "GET"), this.mode = e2.mode || this.mode || null, this.referrer = null, ("GET" === this.method || "HEAD" === this.method) && r2)
        throw new TypeError("Body not allowed for GET or HEAD requests");
      this._initBody(r2);
    }
    function d(t2) {
      var e2 = new FormData();
      return t2.trim().split("&").forEach(function(t3) {
        if (t3) {
          var r2 = t3.split("="), i3 = r2.shift().replace(/\+/g, " "), n2 = r2.join("=").replace(/\+/g, " ");
          e2.append(decodeURIComponent(i3), decodeURIComponent(n2));
        }
      }), e2;
    }
    function y(t2) {
      var e2 = new n();
      return t2.split(/\r?\n/).forEach(function(t3) {
        var r2 = t3.split(":"), i3 = r2.shift().trim();
        if (i3) {
          var n2 = r2.join(":").trim();
          e2.append(i3, n2);
        }
      }), e2;
    }
    function m(t2, e2) {
      e2 || (e2 = {}), this.type = "default", this.status = "status" in e2 ? e2.status : 200, this.ok = this.status >= 200 && this.status < 300, this.statusText = "statusText" in e2 ? e2.statusText : "OK", this.headers = new n(e2.headers), this.url = e2.url || "", this._initBody(t2);
    }
    if (!t.fetch) {
      var v = { searchParams: "URLSearchParams" in t, iterable: "Symbol" in t && "iterator" in Symbol, blob: "FileReader" in t && "Blob" in t && function() {
        try {
          return new Blob(), true;
        } catch (t2) {
          return false;
        }
      }(), formData: "FormData" in t, arrayBuffer: "ArrayBuffer" in t };
      if (v.arrayBuffer)
        var g = ["[object Int8Array]", "[object Uint8Array]", "[object Uint8ClampedArray]", "[object Int16Array]", "[object Uint16Array]", "[object Int32Array]", "[object Uint32Array]", "[object Float32Array]", "[object Float64Array]"], b = function(t2) {
          return t2 && DataView.prototype.isPrototypeOf(t2);
        }, w = ArrayBuffer.isView || function(t2) {
          return t2 && g.indexOf(Object.prototype.toString.call(t2)) > -1;
        };
      n.prototype.append = function(t2, i3) {
        t2 = e(t2), i3 = r(i3);
        var n2 = this.map[t2];
        this.map[t2] = n2 ? n2 + "," + i3 : i3;
      }, n.prototype.delete = function(t2) {
        delete this.map[e(t2)];
      }, n.prototype.get = function(t2) {
        return t2 = e(t2), this.has(t2) ? this.map[t2] : null;
      }, n.prototype.has = function(t2) {
        return this.map.hasOwnProperty(e(t2));
      }, n.prototype.set = function(t2, i3) {
        this.map[e(t2)] = r(i3);
      }, n.prototype.forEach = function(t2, e2) {
        var r2 = this;
        for (var i3 in this.map)
          r2.map.hasOwnProperty(i3) && t2.call(e2, r2.map[i3], i3, r2);
      }, n.prototype.keys = function() {
        var t2 = [];
        return this.forEach(function(e2, r2) {
          t2.push(r2);
        }), i2(t2);
      }, n.prototype.values = function() {
        var t2 = [];
        return this.forEach(function(e2) {
          t2.push(e2);
        }), i2(t2);
      }, n.prototype.entries = function() {
        var t2 = [];
        return this.forEach(function(e2, r2) {
          t2.push([r2, e2]);
        }), i2(t2);
      }, v.iterable && (n.prototype[Symbol.iterator] = n.prototype.entries);
      var _ = ["DELETE", "GET", "HEAD", "OPTIONS", "POST", "PUT"];
      p.prototype.clone = function() {
        return new p(this, { body: this._bodyInit });
      }, c.call(p.prototype), c.call(m.prototype), m.prototype.clone = function() {
        return new m(this._bodyInit, { status: this.status, statusText: this.statusText, headers: new n(this.headers), url: this.url });
      }, m.error = function() {
        var t2 = new m(null, { status: 0, statusText: "" });
        return t2.type = "error", t2;
      };
      var x2 = [301, 302, 303, 307, 308];
      m.redirect = function(t2, e2) {
        if (-1 === x2.indexOf(e2))
          throw new RangeError("Invalid status code");
        return new m(null, { status: e2, headers: { location: t2 } });
      }, t.Headers = n, t.Request = p, t.Response = m, t.fetch = function(t2, e2) {
        return new Promise(function(r2, i3) {
          var n2 = new p(t2, e2), o2 = new XMLHttpRequest();
          o2.onload = function() {
            var t3 = { status: o2.status, statusText: o2.statusText, headers: y(o2.getAllResponseHeaders() || "") };
            t3.url = "responseURL" in o2 ? o2.responseURL : t3.headers.get("X-Request-URL");
            var e3 = "response" in o2 ? o2.response : o2.responseText;
            r2(new m(e3, t3));
          }, o2.onerror = function() {
            i3(new TypeError("Network request failed"));
          }, o2.ontimeout = function() {
            i3(new TypeError("Network request failed"));
          }, o2.open(n2.method, n2.url, true), "include" === n2.credentials && (o2.withCredentials = true), "responseType" in o2 && v.blob && (o2.responseType = "blob"), n2.headers.forEach(function(t3, e3) {
            o2.setRequestHeader(e3, t3);
          }), o2.send(void 0 === n2._bodyInit ? null : n2._bodyInit);
        });
      }, t.fetch.polyfill = true;
    }
  }("undefined" != typeof self ? self : void 0);
  var read = function(t, e, r, i2, n) {
    var o, s, a = 8 * n - i2 - 1, u = (1 << a) - 1, h = u >> 1, l = -7, c = r ? n - 1 : 0, f = r ? -1 : 1, p = t[e + c];
    for (c += f, o = p & (1 << -l) - 1, p >>= -l, l += a; l > 0; o = 256 * o + t[e + c], c += f, l -= 8)
      ;
    for (s = o & (1 << -l) - 1, o >>= -l, l += i2; l > 0; s = 256 * s + t[e + c], c += f, l -= 8)
      ;
    if (0 === o)
      o = 1 - h;
    else {
      if (o === u)
        return s ? NaN : 1 / 0 * (p ? -1 : 1);
      s += Math.pow(2, i2), o -= h;
    }
    return (p ? -1 : 1) * s * Math.pow(2, o - i2);
  };
  var write = function(t, e, r, i2, n, o) {
    var s, a, u, h = 8 * o - n - 1, l = (1 << h) - 1, c = l >> 1, f = 23 === n ? Math.pow(2, -24) - Math.pow(2, -77) : 0, p = i2 ? 0 : o - 1, d = i2 ? 1 : -1, y = e < 0 || 0 === e && 1 / e < 0 ? 1 : 0;
    for (e = Math.abs(e), isNaN(e) || e === 1 / 0 ? (a = isNaN(e) ? 1 : 0, s = l) : (s = Math.floor(Math.log(e) / Math.LN2), e * (u = Math.pow(2, -s)) < 1 && (s--, u *= 2), e += s + c >= 1 ? f / u : f * Math.pow(2, 1 - c), e * u >= 2 && (s++, u /= 2), s + c >= l ? (a = 0, s = l) : s + c >= 1 ? (a = (e * u - 1) * Math.pow(2, n), s += c) : (a = e * Math.pow(2, c - 1) * Math.pow(2, n), s = 0)); n >= 8; t[r + p] = 255 & a, p += d, a /= 256, n -= 8)
      ;
    for (s = s << n | a, h += n; h > 0; t[r + p] = 255 & s, p += d, s /= 256, h -= 8)
      ;
    t[r + p - d] |= 128 * y;
  };
  var index$1 = { read, write };
  var index = Pbf;
  var ieee754 = index$1;
  Pbf.Varint = 0, Pbf.Fixed64 = 1, Pbf.Bytes = 2, Pbf.Fixed32 = 5;
  var SHIFT_LEFT_32 = 4294967296;
  var SHIFT_RIGHT_32 = 1 / SHIFT_LEFT_32;
  Pbf.prototype = { destroy: function() {
    this.buf = null;
  }, readFields: function(t, e, r) {
    var i2 = this;
    for (r = r || this.length; this.pos < r; ) {
      var n = i2.readVarint(), o = n >> 3, s = i2.pos;
      i2.type = 7 & n, t(o, e, i2), i2.pos === s && i2.skip(n);
    }
    return e;
  }, readMessage: function(t, e) {
    return this.readFields(t, e, this.readVarint() + this.pos);
  }, readFixed32: function() {
    var t = readUInt32(this.buf, this.pos);
    return this.pos += 4, t;
  }, readSFixed32: function() {
    var t = readInt32(this.buf, this.pos);
    return this.pos += 4, t;
  }, readFixed64: function() {
    var t = readUInt32(this.buf, this.pos) + readUInt32(this.buf, this.pos + 4) * SHIFT_LEFT_32;
    return this.pos += 8, t;
  }, readSFixed64: function() {
    var t = readUInt32(this.buf, this.pos) + readInt32(this.buf, this.pos + 4) * SHIFT_LEFT_32;
    return this.pos += 8, t;
  }, readFloat: function() {
    var t = ieee754.read(this.buf, this.pos, true, 23, 4);
    return this.pos += 4, t;
  }, readDouble: function() {
    var t = ieee754.read(this.buf, this.pos, true, 52, 8);
    return this.pos += 8, t;
  }, readVarint: function(t) {
    var e, r, i2 = this.buf;
    return r = i2[this.pos++], e = 127 & r, r < 128 ? e : (r = i2[this.pos++], e |= (127 & r) << 7, r < 128 ? e : (r = i2[this.pos++], e |= (127 & r) << 14, r < 128 ? e : (r = i2[this.pos++], e |= (127 & r) << 21, r < 128 ? e : (r = i2[this.pos], e |= (15 & r) << 28, readVarintRemainder(e, t, this)))));
  }, readVarint64: function() {
    return this.readVarint(true);
  }, readSVarint: function() {
    var t = this.readVarint();
    return t % 2 == 1 ? (t + 1) / -2 : t / 2;
  }, readBoolean: function() {
    return Boolean(this.readVarint());
  }, readString: function() {
    var t = this.readVarint() + this.pos, e = readUtf8(this.buf, this.pos, t);
    return this.pos = t, e;
  }, readBytes: function() {
    var t = this.readVarint() + this.pos, e = this.buf.subarray(this.pos, t);
    return this.pos = t, e;
  }, readPackedVarint: function(t, e) {
    var r = this, i2 = readPackedEnd(this);
    for (t = t || []; this.pos < i2; )
      t.push(r.readVarint(e));
    return t;
  }, readPackedSVarint: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readSVarint());
    return t;
  }, readPackedBoolean: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readBoolean());
    return t;
  }, readPackedFloat: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readFloat());
    return t;
  }, readPackedDouble: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readDouble());
    return t;
  }, readPackedFixed32: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readFixed32());
    return t;
  }, readPackedSFixed32: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readSFixed32());
    return t;
  }, readPackedFixed64: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readFixed64());
    return t;
  }, readPackedSFixed64: function(t) {
    var e = this, r = readPackedEnd(this);
    for (t = t || []; this.pos < r; )
      t.push(e.readSFixed64());
    return t;
  }, skip: function(t) {
    var e = 7 & t;
    if (e === Pbf.Varint)
      for (; this.buf[this.pos++] > 127; )
        ;
    else if (e === Pbf.Bytes)
      this.pos = this.readVarint() + this.pos;
    else if (e === Pbf.Fixed32)
      this.pos += 4;
    else {
      if (e !== Pbf.Fixed64)
        throw new Error("Unimplemented type: " + e);
      this.pos += 8;
    }
  }, writeTag: function(t, e) {
    this.writeVarint(t << 3 | e);
  }, realloc: function(t) {
    for (var e = this.length || 16; e < this.pos + t; )
      e *= 2;
    if (e !== this.length) {
      var r = new Uint8Array(e);
      r.set(this.buf), this.buf = r, this.length = e;
    }
  }, finish: function() {
    return this.length = this.pos, this.pos = 0, this.buf.subarray(0, this.length);
  }, writeFixed32: function(t) {
    this.realloc(4), writeInt32(this.buf, t, this.pos), this.pos += 4;
  }, writeSFixed32: function(t) {
    this.realloc(4), writeInt32(this.buf, t, this.pos), this.pos += 4;
  }, writeFixed64: function(t) {
    this.realloc(8), writeInt32(this.buf, -1 & t, this.pos), writeInt32(this.buf, Math.floor(t * SHIFT_RIGHT_32), this.pos + 4), this.pos += 8;
  }, writeSFixed64: function(t) {
    this.realloc(8), writeInt32(this.buf, -1 & t, this.pos), writeInt32(this.buf, Math.floor(t * SHIFT_RIGHT_32), this.pos + 4), this.pos += 8;
  }, writeVarint: function(t) {
    if ((t = +t || 0) > 268435455 || t < 0)
      return void writeBigVarint(t, this);
    this.realloc(4), this.buf[this.pos++] = 127 & t | (t > 127 ? 128 : 0), t <= 127 || (this.buf[this.pos++] = 127 & (t >>>= 7) | (t > 127 ? 128 : 0), t <= 127 || (this.buf[this.pos++] = 127 & (t >>>= 7) | (t > 127 ? 128 : 0), t <= 127 || (this.buf[this.pos++] = t >>> 7 & 127)));
  }, writeSVarint: function(t) {
    this.writeVarint(t < 0 ? 2 * -t - 1 : 2 * t);
  }, writeBoolean: function(t) {
    this.writeVarint(Boolean(t));
  }, writeString: function(t) {
    t = String(t), this.realloc(4 * t.length), this.pos++;
    var e = this.pos;
    this.pos = writeUtf8(this.buf, t, this.pos);
    var r = this.pos - e;
    r >= 128 && makeRoomForExtraLength(e, r, this), this.pos = e - 1, this.writeVarint(r), this.pos += r;
  }, writeFloat: function(t) {
    this.realloc(4), ieee754.write(this.buf, t, this.pos, true, 23, 4), this.pos += 4;
  }, writeDouble: function(t) {
    this.realloc(8), ieee754.write(this.buf, t, this.pos, true, 52, 8), this.pos += 8;
  }, writeBytes: function(t) {
    var e = this, r = t.length;
    this.writeVarint(r), this.realloc(r);
    for (var i2 = 0; i2 < r; i2++)
      e.buf[e.pos++] = t[i2];
  }, writeRawMessage: function(t, e) {
    this.pos++;
    var r = this.pos;
    t(e, this);
    var i2 = this.pos - r;
    i2 >= 128 && makeRoomForExtraLength(r, i2, this), this.pos = r - 1, this.writeVarint(i2), this.pos += i2;
  }, writeMessage: function(t, e, r) {
    this.writeTag(t, Pbf.Bytes), this.writeRawMessage(e, r);
  }, writePackedVarint: function(t, e) {
    this.writeMessage(t, writePackedVarint, e);
  }, writePackedSVarint: function(t, e) {
    this.writeMessage(t, writePackedSVarint, e);
  }, writePackedBoolean: function(t, e) {
    this.writeMessage(t, writePackedBoolean, e);
  }, writePackedFloat: function(t, e) {
    this.writeMessage(t, writePackedFloat, e);
  }, writePackedDouble: function(t, e) {
    this.writeMessage(t, writePackedDouble, e);
  }, writePackedFixed32: function(t, e) {
    this.writeMessage(t, writePackedFixed32, e);
  }, writePackedSFixed32: function(t, e) {
    this.writeMessage(t, writePackedSFixed32, e);
  }, writePackedFixed64: function(t, e) {
    this.writeMessage(t, writePackedFixed64, e);
  }, writePackedSFixed64: function(t, e) {
    this.writeMessage(t, writePackedSFixed64, e);
  }, writeBytesField: function(t, e) {
    this.writeTag(t, Pbf.Bytes), this.writeBytes(e);
  }, writeFixed32Field: function(t, e) {
    this.writeTag(t, Pbf.Fixed32), this.writeFixed32(e);
  }, writeSFixed32Field: function(t, e) {
    this.writeTag(t, Pbf.Fixed32), this.writeSFixed32(e);
  }, writeFixed64Field: function(t, e) {
    this.writeTag(t, Pbf.Fixed64), this.writeFixed64(e);
  }, writeSFixed64Field: function(t, e) {
    this.writeTag(t, Pbf.Fixed64), this.writeSFixed64(e);
  }, writeVarintField: function(t, e) {
    this.writeTag(t, Pbf.Varint), this.writeVarint(e);
  }, writeSVarintField: function(t, e) {
    this.writeTag(t, Pbf.Varint), this.writeSVarint(e);
  }, writeStringField: function(t, e) {
    this.writeTag(t, Pbf.Bytes), this.writeString(e);
  }, writeFloatField: function(t, e) {
    this.writeTag(t, Pbf.Fixed32), this.writeFloat(e);
  }, writeDoubleField: function(t, e) {
    this.writeTag(t, Pbf.Fixed64), this.writeDouble(e);
  }, writeBooleanField: function(t, e) {
    this.writeVarintField(t, Boolean(e));
  } };
  var index$5 = Point$1;
  Point$1.prototype = { clone: function() {
    return new Point$1(this.x, this.y);
  }, add: function(t) {
    return this.clone()._add(t);
  }, sub: function(t) {
    return this.clone()._sub(t);
  }, mult: function(t) {
    return this.clone()._mult(t);
  }, div: function(t) {
    return this.clone()._div(t);
  }, rotate: function(t) {
    return this.clone()._rotate(t);
  }, matMult: function(t) {
    return this.clone()._matMult(t);
  }, unit: function() {
    return this.clone()._unit();
  }, perp: function() {
    return this.clone()._perp();
  }, round: function() {
    return this.clone()._round();
  }, mag: function() {
    return Math.sqrt(this.x * this.x + this.y * this.y);
  }, equals: function(t) {
    return this.x === t.x && this.y === t.y;
  }, dist: function(t) {
    return Math.sqrt(this.distSqr(t));
  }, distSqr: function(t) {
    var e = t.x - this.x, r = t.y - this.y;
    return e * e + r * r;
  }, angle: function() {
    return Math.atan2(this.y, this.x);
  }, angleTo: function(t) {
    return Math.atan2(this.y - t.y, this.x - t.x);
  }, angleWith: function(t) {
    return this.angleWithSep(t.x, t.y);
  }, angleWithSep: function(t, e) {
    return Math.atan2(this.x * e - this.y * t, this.x * t + this.y * e);
  }, _matMult: function(t) {
    var e = t[0] * this.x + t[1] * this.y, r = t[2] * this.x + t[3] * this.y;
    return this.x = e, this.y = r, this;
  }, _add: function(t) {
    return this.x += t.x, this.y += t.y, this;
  }, _sub: function(t) {
    return this.x -= t.x, this.y -= t.y, this;
  }, _mult: function(t) {
    return this.x *= t, this.y *= t, this;
  }, _div: function(t) {
    return this.x /= t, this.y /= t, this;
  }, _unit: function() {
    return this._div(this.mag()), this;
  }, _perp: function() {
    var t = this.y;
    return this.y = this.x, this.x = -t, this;
  }, _rotate: function(t) {
    var e = Math.cos(t), r = Math.sin(t), i2 = e * this.x - r * this.y, n = r * this.x + e * this.y;
    return this.x = i2, this.y = n, this;
  }, _round: function() {
    return this.x = Math.round(this.x), this.y = Math.round(this.y), this;
  } }, Point$1.convert = function(t) {
    return t instanceof Point$1 ? t : Array.isArray(t) ? new Point$1(t[0], t[1]) : t;
  };
  var Point = index$5;
  var vectortilefeature = VectorTileFeature$2;
  VectorTileFeature$2.types = ["Unknown", "Point", "LineString", "Polygon"], VectorTileFeature$2.prototype.loadGeometry = function() {
    var t = this._pbf;
    t.pos = this._geometry;
    for (var e, r = t.readVarint() + t.pos, i2 = 1, n = 0, o = 0, s = 0, a = []; t.pos < r; ) {
      if (!n) {
        var u = t.readVarint();
        i2 = 7 & u, n = u >> 3;
      }
      if (n--, 1 === i2 || 2 === i2)
        o += t.readSVarint(), s += t.readSVarint(), 1 === i2 && (e && a.push(e), e = []), e.push(new Point(o, s));
      else {
        if (7 !== i2)
          throw new Error("unknown command " + i2);
        e && e.push(e[0].clone());
      }
    }
    return e && a.push(e), a;
  }, VectorTileFeature$2.prototype.bbox = function() {
    var t = this._pbf;
    t.pos = this._geometry;
    for (var e = t.readVarint() + t.pos, r = 1, i2 = 0, n = 0, o = 0, s = 1 / 0, a = -1 / 0, u = 1 / 0, h = -1 / 0; t.pos < e; ) {
      if (!i2) {
        var l = t.readVarint();
        r = 7 & l, i2 = l >> 3;
      }
      if (i2--, 1 === r || 2 === r)
        n += t.readSVarint(), o += t.readSVarint(), n < s && (s = n), n > a && (a = n), o < u && (u = o), o > h && (h = o);
      else if (7 !== r)
        throw new Error("unknown command " + r);
    }
    return [s, u, a, h];
  }, VectorTileFeature$2.prototype.toGeoJSON = function(t, e, r) {
    function i2(t2) {
      for (var e2 = 0; e2 < t2.length; e2++) {
        var r2 = t2[e2], i3 = 180 - 360 * (r2.y + u) / s;
        t2[e2] = [360 * (r2.x + a) / s - 180, 360 / Math.PI * Math.atan(Math.exp(i3 * Math.PI / 180)) - 90];
      }
    }
    var n, o, s = this.extent * Math.pow(2, r), a = this.extent * t, u = this.extent * e, h = this.loadGeometry(), l = VectorTileFeature$2.types[this.type];
    switch (this.type) {
      case 1:
        var c = [];
        for (n = 0; n < h.length; n++)
          c[n] = h[n][0];
        h = c, i2(h);
        break;
      case 2:
        for (n = 0; n < h.length; n++)
          i2(h[n]);
        break;
      case 3:
        for (h = classifyRings(h), n = 0; n < h.length; n++)
          for (o = 0; o < h[n].length; o++)
            i2(h[n][o]);
    }
    1 === h.length ? h = h[0] : l = "Multi" + l;
    var f = { type: "Feature", geometry: { type: l, coordinates: h }, properties: this.properties };
    return "id" in this && (f.id = this.id), f;
  };
  var VectorTileFeature$1 = vectortilefeature;
  var vectortilelayer = VectorTileLayer$2;
  VectorTileLayer$2.prototype.feature = function(t) {
    if (t < 0 || t >= this._features.length)
      throw new Error("feature index out of bounds");
    this._pbf.pos = this._features[t];
    var e = this._pbf.readVarint() + this._pbf.pos;
    return new VectorTileFeature$1(this._pbf, e, this.extent, this._keys, this._values);
  };
  var VectorTileLayer$1 = vectortilelayer;
  var vectortile = VectorTile$1;
  var VectorTile = vectortile;
  L.SVG.Tile = L.SVG.extend({ initialize: function(t, e, r) {
    L.SVG.prototype.initialize.call(this, r), this._tileCoord = t, this._size = e, this._initContainer(), this._container.setAttribute("width", this._size.x), this._container.setAttribute("height", this._size.y), this._container.setAttribute("viewBox", [0, 0, this._size.x, this._size.y].join(" ")), this._layers = {};
  }, getCoord: function() {
    return this._tileCoord;
  }, getContainer: function() {
    return this._container;
  }, onAdd: L.Util.falseFn, addTo: function(t) {
    if (this._map = t, this.options.interactive)
      for (var e in this._layers) {
        var r = this._layers[e];
        r._path.style.pointerEvents = "auto", this._map._targets[L.stamp(r._path)] = r;
      }
  }, removeFrom: function(t) {
    if (this.options.interactive)
      for (var e in this._layers) {
        var r = this._layers[e];
        delete this._map._targets[L.stamp(r._path)];
      }
    delete this._map;
  }, _initContainer: function() {
    L.SVG.prototype._initContainer.call(this);
    L.SVG.create("rect");
  }, _addPath: function(t) {
    this._rootGroup.appendChild(t._path), this._layers[L.stamp(t)] = t;
  }, _updateIcon: function(t) {
    var e = t._path = L.SVG.create("image"), r = t.options.icon, i2 = r.options, n = L.point(i2.iconSize), o = i2.iconAnchor || n && n.divideBy(2, true), s = t._point.subtract(o);
    e.setAttribute("x", s.x), e.setAttribute("y", s.y), e.setAttribute("width", n.x + "px"), e.setAttribute("height", n.y + "px"), e.setAttribute("href", i2.iconUrl);
  } }), L.svg.tile = function(t, e, r) {
    return new L.SVG.Tile(t, e, r);
  };
  var Symbolizer = L.Class.extend({ render: function(t, e) {
    this._renderer = t, this.options = e, t._initPath(this), t._updateStyle(this);
  }, updateStyle: function(t, e) {
    this.options = e, t._updateStyle(this);
  }, _getPixelBounds: function() {
    for (var t = this._parts, e = L.bounds([]), r = 0; r < t.length; r++)
      for (var i2 = t[r], n = 0; n < i2.length; n++)
        e.extend(i2[n]);
    var o = this._clickTolerance(), s = new L.Point(o, o);
    return e.min._subtract(s), e.max._add(s), e;
  }, _clickTolerance: L.Path.prototype._clickTolerance });
  var PolyBase = { _makeFeatureParts: function(t, e) {
    var r, i2 = t.geometry;
    this._parts = [];
    for (var n = 0; n < i2.length; n++) {
      for (var o = i2[n], s = [], a = 0; a < o.length; a++)
        r = o[a], s.push(L.point(r).scaleBy(e));
      this._parts.push(s);
    }
  }, makeInteractive: function() {
    this._pxBounds = this._getPixelBounds();
  } };
  var PointSymbolizer = L.CircleMarker.extend({ includes: Symbolizer.prototype, statics: { iconCache: {} }, initialize: function(t, e) {
    this.properties = t.properties, this._makeFeatureParts(t, e);
  }, render: function(t, e) {
    Symbolizer.prototype.render.call(this, t, e), this._radius = e.radius || L.CircleMarker.prototype.options.radius, this._updatePath();
  }, _makeFeatureParts: function(t, e) {
    var r = t.geometry[0];
    "object" == typeof r[0] && "x" in r[0] ? (this._point = L.point(r[0]).scaleBy(e), this._empty = L.Util.falseFn) : (this._point = L.point(r).scaleBy(e), this._empty = L.Util.falseFn);
  }, makeInteractive: function() {
    this._updateBounds();
  }, updateStyle: function(t, e) {
    return this._radius = e.radius || this._radius, this._updateBounds(), Symbolizer.prototype.updateStyle.call(this, t, e);
  }, _updateBounds: function() {
    var t = this.options.icon;
    if (t) {
      var e = L.point(t.options.iconSize), r = t.options.iconAnchor || e && e.divideBy(2, true), i2 = this._point.subtract(r);
      this._pxBounds = new L.Bounds(i2, i2.add(t.options.iconSize));
    } else
      L.CircleMarker.prototype._updateBounds.call(this);
  }, _updatePath: function() {
    this.options.icon ? this._renderer._updateIcon(this) : L.CircleMarker.prototype._updatePath.call(this);
  }, _getImage: function() {
    if (this.options.icon) {
      var t = this.options.icon.options.iconUrl, e = PointSymbolizer.iconCache[t];
      if (!e) {
        var r = this.options.icon;
        e = PointSymbolizer.iconCache[t] = r.createIcon();
      }
      return e;
    }
    return null;
  }, _containsPoint: function(t) {
    return this.options.icon ? this._pxBounds.contains(t) : L.CircleMarker.prototype._containsPoint.call(this, t);
  } });
  var LineSymbolizer = L.Polyline.extend({ includes: [Symbolizer.prototype, PolyBase], initialize: function(t, e) {
    this.properties = t.properties, this._makeFeatureParts(t, e);
  }, render: function(t, e) {
    e.fill = false, Symbolizer.prototype.render.call(this, t, e), this._updatePath();
  }, updateStyle: function(t, e) {
    e.fill = false, Symbolizer.prototype.updateStyle.call(this, t, e);
  } });
  var FillSymbolizer = L.Polygon.extend({ includes: [Symbolizer.prototype, PolyBase], initialize: function(t, e) {
    this.properties = t.properties, this._makeFeatureParts(t, e);
  }, render: function(t, e) {
    Symbolizer.prototype.render.call(this, t, e), this._updatePath();
  } });
  L.VectorGrid = L.GridLayer.extend({ options: { rendererFactory: L.svg.tile, vectorTileLayerStyles: {}, interactive: false }, initialize: function(t) {
    L.setOptions(this, t), L.GridLayer.prototype.initialize.apply(this, arguments), this.options.getFeatureId && (this._vectorTiles = {}, this._overriddenStyles = {}, this.on("tileunload", function(t2) {
      var e = this._tileCoordsToKey(t2.coords), r = this._vectorTiles[e];
      r && this._map && r.removeFrom(this._map), delete this._vectorTiles[e];
    }, this)), this._dataLayerNames = {};
  }, createTile: function(t, e) {
    var r = this.options.getFeatureId, i2 = this.getTileSize(), n = this.options.rendererFactory(t, i2, this.options), o = this._getVectorTilePromise(t);
    return r && (this._vectorTiles[this._tileCoordsToKey(t)] = n, n._features = {}), o.then(function(i3) {
      for (var o2 in i3.layers) {
        this._dataLayerNames[o2] = true;
        for (var s = i3.layers[o2], a = this.getTileSize().divideBy(s.extent), u = this.options.vectorTileLayerStyles[o2] || L.Path.prototype.options, h = 0; h < s.features.length; h++) {
          var l, c = s.features[h], f = u;
          if (r) {
            l = this.options.getFeatureId(c);
            var p = this._overriddenStyles[l];
            p && (f = p[o2] ? p[o2] : p);
          }
          if (f instanceof Function && (f = f(c.properties, t.z)), f instanceof Array || (f = [f]), f.length) {
            for (var d = this._createLayer(c, a), y = 0; y < f.length; y++) {
              var m = L.extend({}, L.Path.prototype.options, f[y]);
              d.render(n, m), n._addPath(d);
            }
            this.options.interactive && d.makeInteractive(), r && (n._features[l] = { layerName: o2, feature: d });
          }
        }
      }
      null != this._map && n.addTo(this._map), L.Util.requestAnimFrame(e.bind(t, null, null));
    }.bind(this)), n.getContainer();
  }, setFeatureStyle: function(t, e) {
    this._overriddenStyles[t] = e;
    for (var r in this._vectorTiles) {
      var i2 = this._vectorTiles[r], n = i2._features, o = n[t];
      if (o) {
        var s = o.feature, a = e;
        e[o.layerName] && (a = e[o.layerName]), this._updateStyles(s, i2, a);
      }
    }
    return this;
  }, resetFeatureStyle: function(t) {
    delete this._overriddenStyles[t];
    for (var e in this._vectorTiles) {
      var r = this._vectorTiles[e], i2 = r._features, n = i2[t];
      if (n) {
        var o = n.feature, s = this.options.vectorTileLayerStyles[n.layerName] || L.Path.prototype.options;
        this._updateStyles(o, r, s);
      }
    }
    return this;
  }, getDataLayerNames: function() {
    return Object.keys(this._dataLayerNames);
  }, _updateStyles: function(t, e, r) {
    (r = r instanceof Function ? r(t.properties, e.getCoord().z) : r) instanceof Array || (r = [r]);
    for (var i2 = 0; i2 < r.length; i2++) {
      var n = L.extend({}, L.Path.prototype.options, r[i2]);
      t.updateStyle(e, n);
    }
  }, _createLayer: function(t, e, r) {
    var i2;
    switch (t.type) {
      case 1:
        i2 = new PointSymbolizer(t, e);
        break;
      case 2:
        i2 = new LineSymbolizer(t, e);
        break;
      case 3:
        i2 = new FillSymbolizer(t, e);
    }
    return this.options.interactive && i2.addEventParent(this), i2;
  } }), L.vectorGrid = function(t) {
    return new L.VectorGrid(t);
  }, L.VectorGrid.Protobuf = L.VectorGrid.extend({ options: { subdomains: "abc", fetchOptions: {} }, initialize: function(t, e) {
    this._url = t, L.VectorGrid.prototype.initialize.call(this, e);
  }, setUrl: function(t, e) {
    return this._url = t, e || this.redraw(), this;
  }, _getSubdomain: L.TileLayer.prototype._getSubdomain, _getVectorTilePromise: function(t) {
    var e = { s: this._getSubdomain(t), x: t.x, y: t.y, z: t.z };
    if (this._map && !this._map.options.crs.infinite) {
      var r = this._globalTileRange.max.y - t.y;
      this.options.tms && (e.y = r), e["-y"] = r;
    }
    var i2 = L.Util.template(this._url, L.extend(e, this.options));
    return fetch(i2, this.options.fetchOptions).then(function(t2) {
      return t2.ok ? t2.blob().then(function(t3) {
        var e2 = new FileReader();
        return new Promise(function(r2) {
          e2.addEventListener("loadend", function() {
            var t4 = new index(e2.result);
            return r2(new VectorTile(t4));
          }), e2.readAsArrayBuffer(t3);
        });
      }) : { layers: [] };
    }).then(function(t2) {
      for (var e2 in t2.layers) {
        for (var r2 = [], i3 = 0; i3 < t2.layers[e2].length; i3++) {
          var n = t2.layers[e2].feature(i3);
          n.geometry = n.loadGeometry(), r2.push(n);
        }
        t2.layers[e2].features = r2;
      }
      return t2;
    });
  } }), L.vectorGrid.protobuf = function(t, e) {
    return new L.VectorGrid.Protobuf(t, e);
  };
  var workerCode = __$strToBlobUri('"use strict";function simplify$1(e,t){var r,n,o,i,a=t*t,s=e.length,l=0,u=s-1,c=[];for(e[l][2]=1,e[u][2]=1;u;){for(n=0,r=l+1;r<u;r++)(o=getSqSegDist(e[r],e[l],e[u]))>n&&(i=r,n=o);n>a?(e[i][2]=n,c.push(l),c.push(i),l=i):(u=c.pop(),l=c.pop())}}function getSqSegDist(e,t,r){var n=t[0],o=t[1],i=r[0],a=r[1],s=e[0],l=e[1],u=i-n,c=a-o;if(0!==u||0!==c){var f=((s-n)*u+(l-o)*c)/(u*u+c*c);f>1?(n=i,o=a):f>0&&(n+=u*f,o+=c*f)}return u=s-n,c=l-o,u*u+c*c}function convert$1(e,t){var r=[];if("FeatureCollection"===e.type)for(var n=0;n<e.features.length;n++)convertFeature(r,e.features[n],t);else"Feature"===e.type?convertFeature(r,e,t):convertFeature(r,{geometry:e},t);return r}function convertFeature(e,t,r){if(null!==t.geometry){var n,o,i,a,s=t.geometry,l=s.type,u=s.coordinates,c=t.properties;if("Point"===l)e.push(create(c,1,[projectPoint(u)]));else if("MultiPoint"===l)e.push(create(c,1,project(u)));else if("LineString"===l)e.push(create(c,2,[project(u,r)]));else if("MultiLineString"===l||"Polygon"===l){for(i=[],n=0;n<u.length;n++)a=project(u[n],r),"Polygon"===l&&(a.outer=0===n),i.push(a);e.push(create(c,"Polygon"===l?3:2,i))}else if("MultiPolygon"===l){for(i=[],n=0;n<u.length;n++)for(o=0;o<u[n].length;o++)a=project(u[n][o],r),a.outer=0===o,i.push(a);e.push(create(c,3,i))}else{if("GeometryCollection"!==l)throw new Error("Input data is not a valid GeoJSON object.");for(n=0;n<s.geometries.length;n++)convertFeature(e,{geometry:s.geometries[n],properties:c},r)}}}function create(e,t,r){var n={geometry:r,type:t,tags:e||null,min:[2,1],max:[-1,0]};return calcBBox(n),n}function project(e,t){for(var r=[],n=0;n<e.length;n++)r.push(projectPoint(e[n]));return t&&(simplify(r,t),calcSize(r)),r}function projectPoint(e){var t=Math.sin(e[1]*Math.PI/180),r=e[0]/360+.5,n=.5-.25*Math.log((1+t)/(1-t))/Math.PI;return n=n<0?0:n>1?1:n,[r,n,0]}function calcSize(e){for(var t,r,n=0,o=0,i=0;i<e.length-1;i++)t=r||e[i],r=e[i+1],n+=t[0]*r[1]-r[0]*t[1],o+=Math.abs(r[0]-t[0])+Math.abs(r[1]-t[1]);e.area=Math.abs(n/2),e.dist=o}function calcBBox(e){var t=e.geometry,r=e.min,n=e.max;if(1===e.type)calcRingBBox(r,n,t);else for(var o=0;o<t.length;o++)calcRingBBox(r,n,t[o]);return e}function calcRingBBox(e,t,r){for(var n,o=0;o<r.length;o++)n=r[o],e[0]=Math.min(n[0],e[0]),t[0]=Math.max(n[0],t[0]),e[1]=Math.min(n[1],e[1]),t[1]=Math.max(n[1],t[1])}function transformTile(e,t){if(e.transformed)return e;var r,n,o,i=e.z2,a=e.x,s=e.y;for(r=0;r<e.features.length;r++){var l=e.features[r],u=l.geometry;if(1===l.type)for(n=0;n<u.length;n++)u[n]=transformPoint(u[n],t,i,a,s);else for(n=0;n<u.length;n++){var c=u[n];for(o=0;o<c.length;o++)c[o]=transformPoint(c[o],t,i,a,s)}}return e.transformed=!0,e}function transformPoint(e,t,r,n,o){return[Math.round(t*(e[0]*r-n)),Math.round(t*(e[1]*r-o))]}function clip$1(e,t,r,n,o,i,a,s){if(r/=t,n/=t,a>=r&&s<=n)return e;if(a>n||s<r)return null;for(var l=[],u=0;u<e.length;u++){var c,f,p=e[u],h=p.geometry,m=p.type;if(c=p.min[o],f=p.max[o],c>=r&&f<=n)l.push(p);else if(!(c>n||f<r)){var g=1===m?clipPoints(h,r,n,o):clipGeometry(h,r,n,o,i,3===m);g.length&&l.push({geometry:g,type:m,tags:e[u].tags||null,min:p.min,max:p.max})}}return l.length?l:null}function clipPoints(e,t,r,n){for(var o=[],i=0;i<e.length;i++){var a=e[i],s=a[n];s>=t&&s<=r&&o.push(a)}return o}function clipGeometry(e,t,r,n,o,i){for(var a=[],s=0;s<e.length;s++){var l,u,c,f=0,p=0,h=null,m=e[s],g=m.area,d=m.dist,v=m.outer,y=m.length,x=[];for(u=0;u<y-1;u++)l=h||m[u],h=m[u+1],f=p||l[n],p=h[n],f<t?p>r?(x.push(o(l,h,t),o(l,h,r)),i||(x=newSlice(a,x,g,d,v))):p>=t&&x.push(o(l,h,t)):f>r?p<t?(x.push(o(l,h,r),o(l,h,t)),i||(x=newSlice(a,x,g,d,v))):p<=r&&x.push(o(l,h,r)):(x.push(l),p<t?(x.push(o(l,h,t)),i||(x=newSlice(a,x,g,d,v))):p>r&&(x.push(o(l,h,r)),i||(x=newSlice(a,x,g,d,v))));l=m[y-1],f=l[n],f>=t&&f<=r&&x.push(l),c=x[x.length-1],i&&c&&(x[0][0]!==c[0]||x[0][1]!==c[1])&&x.push(x[0]),newSlice(a,x,g,d,v)}return a}function newSlice(e,t,r,n,o){return t.length&&(t.area=r,t.dist=n,void 0!==o&&(t.outer=o),e.push(t)),[]}function wrap$1(e,t,r){var n=e,o=clip$2(e,1,-1-t,t,0,r,-1,2),i=clip$2(e,1,1-t,2+t,0,r,-1,2);return(o||i)&&(n=clip$2(e,1,-t,1+t,0,r,-1,2),o&&(n=shiftFeatureCoords(o,1).concat(n)),i&&(n=n.concat(shiftFeatureCoords(i,-1)))),n}function shiftFeatureCoords(e,t){for(var r=[],n=0;n<e.length;n++){var o,i=e[n],a=i.type;if(1===a)o=shiftCoords(i.geometry,t);else{o=[];for(var s=0;s<i.geometry.length;s++)o.push(shiftCoords(i.geometry[s],t))}r.push({geometry:o,type:a,tags:i.tags,min:[i.min[0]+t,i.min[1]],max:[i.max[0]+t,i.max[1]]})}return r}function shiftCoords(e,t){var r=[];r.area=e.area,r.dist=e.dist;for(var n=0;n<e.length;n++)r.push([e[n][0]+t,e[n][1],e[n][2]]);return r}function createTile$1(e,t,r,n,o,i){for(var a={features:[],numPoints:0,numSimplified:0,numFeatures:0,source:null,x:r,y:n,z2:t,transformed:!1,min:[2,1],max:[-1,0]},s=0;s<e.length;s++){a.numFeatures++,addFeature(a,e[s],o,i);var l=e[s].min,u=e[s].max;l[0]<a.min[0]&&(a.min[0]=l[0]),l[1]<a.min[1]&&(a.min[1]=l[1]),u[0]>a.max[0]&&(a.max[0]=u[0]),u[1]>a.max[1]&&(a.max[1]=u[1])}return a}function addFeature(e,t,r,n){var o,i,a,s,l=t.geometry,u=t.type,c=[],f=r*r;if(1===u)for(o=0;o<l.length;o++)c.push(l[o]),e.numPoints++,e.numSimplified++;else for(o=0;o<l.length;o++)if(a=l[o],n||!(2===u&&a.dist<r||3===u&&a.area<f)){var p=[];for(i=0;i<a.length;i++)s=a[i],(n||s[2]>f)&&(p.push(s),e.numSimplified++),e.numPoints++;3===u&&rewind(p,a.outer),c.push(p)}else e.numPoints+=a.length;c.length&&e.features.push({geometry:c,type:u,tags:t.tags||null})}function rewind(e,t){signedArea(e)<0===t&&e.reverse()}function signedArea(e){for(var t,r,n=0,o=0,i=e.length,a=i-1;o<i;a=o++)t=e[o],r=e[a],n+=(r[0]-t[0])*(t[1]+r[1]);return n}function geojsonvt(e,t){return new GeoJSONVT(e,t)}function GeoJSONVT(e,t){t=this.options=extend(Object.create(this.options),t);var r=t.debug;r&&console.time("preprocess data");var n=1<<t.maxZoom,o=convert(e,t.tolerance/(n*t.extent));this.tiles={},this.tileCoords=[],r&&(console.timeEnd("preprocess data"),console.log("index: maxZoom: %d, maxPoints: %d",t.indexMaxZoom,t.indexMaxPoints),console.time("generate tiles"),this.stats={},this.total=0),o=wrap(o,t.buffer/t.extent,intersectX),o.length&&this.splitTile(o,0,0,0),r&&(o.length&&console.log("features: %d, points: %d",this.tiles[0].numFeatures,this.tiles[0].numPoints),console.timeEnd("generate tiles"),console.log("tiles generated:",this.total,JSON.stringify(this.stats)))}function toID(e,t,r){return 32*((1<<e)*r+t)+e}function intersectX(e,t,r){return[r,(r-e[0])*(t[1]-e[1])/(t[0]-e[0])+e[1],1]}function intersectY(e,t,r){return[(r-e[1])*(t[0]-e[0])/(t[1]-e[1])+e[0],r,1]}function extend(e,t){for(var r in t)e[r]=t[r];return e}function isClippedSquare(e,t,r){var n=e.source;if(1!==n.length)return!1;var o=n[0];if(3!==o.type||o.geometry.length>1)return!1;var i=o.geometry[0].length;if(5!==i)return!1;for(var a=0;a<i;a++){var s=transform.point(o.geometry[0][a],t,e.z2,e.x,e.y);if(s[0]!==-r&&s[0]!==t+r||s[1]!==-r&&s[1]!==t+r)return!1}return!0}function feature$1(e,t){var r=t.id,n=t.bbox,o=null==t.properties?{}:t.properties,i=object(e,t);return null==r&&null==n?{type:"Feature",properties:o,geometry:i}:null==n?{type:"Feature",id:r,properties:o,geometry:i}:{type:"Feature",id:r,bbox:n,properties:o,geometry:i}}function object(e,t){function r(e,t){t.length&&t.pop();for(var r=u[e<0?~e:e],n=0,o=r.length;n<o;++n)t.push(l(r[n].slice(),n));e<0&&reverse(t,o)}function n(e){return l(e.slice())}function o(e){for(var t=[],n=0,o=e.length;n<o;++n)r(e[n],t);return t.length<2&&t.push(t[0].slice()),t}function i(e){for(var t=o(e);t.length<4;)t.push(t[0].slice());return t}function a(e){return e.map(i)}function s(e){var t,r=e.type;switch(r){case"GeometryCollection":return{type:r,geometries:e.geometries.map(s)};case"Point":t=n(e.coordinates);break;case"MultiPoint":t=e.coordinates.map(n);break;case"LineString":t=o(e.arcs);break;case"MultiLineString":t=e.arcs.map(o);break;case"Polygon":t=a(e.arcs);break;case"MultiPolygon":t=e.arcs.map(a);break;default:return null}return{type:r,coordinates:t}}var l=transform$3(e),u=e.arcs;return s(t)}function extractArcs(e,t,r){function n(e){var t=e<0?~e:e;(c[t]||(c[t]=[])).push({i:e,g:l})}function o(e){e.forEach(n)}function i(e){e.forEach(o)}function a(e){e.forEach(i)}function s(e){switch(l=e,e.type){case"GeometryCollection":e.geometries.forEach(s);break;case"LineString":o(e.arcs);break;case"MultiLineString":case"Polygon":i(e.arcs);break;case"MultiPolygon":a(e.arcs)}}var l,u=[],c=[];return s(t),c.forEach(null==r?function(e){u.push(e[0].i)}:function(e){r(e[0].g,e[e.length-1].g)&&u.push(e[0].i)}),u}function planarRingArea(e){for(var t,r=-1,n=e.length,o=e[n-1],i=0;++r<n;)t=o,o=e[r],i+=t[0]*o[1]-t[1]*o[0];return Math.abs(i)}var simplify_1=simplify$1,convert_1=convert$1,simplify=simplify_1,tile=transformTile,point=transformPoint,transform$1={tile:tile,point:point},clip_1=clip$1,clip$2=clip_1,wrap_1=wrap$1,tile$1=createTile$1,index=geojsonvt,convert=convert_1,transform=transform$1,clip=clip_1,wrap=wrap_1,createTile=tile$1;GeoJSONVT.prototype.options={maxZoom:14,indexMaxZoom:5,indexMaxPoints:1e5,solidChildren:!1,tolerance:3,extent:4096,buffer:64,debug:0},GeoJSONVT.prototype.splitTile=function(e,t,r,n,o,i,a){for(var s=this,l=[e,t,r,n],u=this.options,c=u.debug,f=null;l.length;){n=l.pop(),r=l.pop(),t=l.pop(),e=l.pop();var p=1<<t,h=toID(t,r,n),m=s.tiles[h],g=t===u.maxZoom?0:u.tolerance/(p*u.extent);if(!m&&(c>1&&console.time("creation"),m=s.tiles[h]=createTile(e,p,r,n,g,t===u.maxZoom),s.tileCoords.push({z:t,x:r,y:n}),c)){c>1&&(console.log("tile z%d-%d-%d (features: %d, points: %d, simplified: %d)",t,r,n,m.numFeatures,m.numPoints,m.numSimplified),console.timeEnd("creation"));var d="z"+t;s.stats[d]=(s.stats[d]||0)+1,s.total++}if(m.source=e,o){if(t===u.maxZoom||t===o)continue;var v=1<<o-t;if(r!==Math.floor(i/v)||n!==Math.floor(a/v))continue}else if(t===u.indexMaxZoom||m.numPoints<=u.indexMaxPoints)continue;if(u.solidChildren||!isClippedSquare(m,u.extent,u.buffer)){m.source=null,c>1&&console.time("clipping");var y,x,b,M,P,S,w=.5*u.buffer/u.extent,$=.5-w,C=.5+w,F=1+w;y=x=b=M=null,P=clip(e,p,r-w,r+C,0,intersectX,m.min[0],m.max[0]),S=clip(e,p,r+$,r+F,0,intersectX,m.min[0],m.max[0]),P&&(y=clip(P,p,n-w,n+C,1,intersectY,m.min[1],m.max[1]),x=clip(P,p,n+$,n+F,1,intersectY,m.min[1],m.max[1])),S&&(b=clip(S,p,n-w,n+C,1,intersectY,m.min[1],m.max[1]),M=clip(S,p,n+$,n+F,1,intersectY,m.min[1],m.max[1])),c>1&&console.timeEnd("clipping"),y&&l.push(y,t+1,2*r,2*n),x&&l.push(x,t+1,2*r,2*n+1),b&&l.push(b,t+1,2*r+1,2*n),M&&l.push(M,t+1,2*r+1,2*n+1)}else o&&(f=t)}return f},GeoJSONVT.prototype.getTile=function(e,t,r){var n=this,o=this.options,i=o.extent,a=o.debug,s=1<<e;t=(t%s+s)%s;var l=toID(e,t,r);if(this.tiles[l])return transform.tile(this.tiles[l],i);a>1&&console.log("drilling down to z%d-%d-%d",e,t,r);for(var u,c=e,f=t,p=r;!u&&c>0;)c--,f=Math.floor(f/2),p=Math.floor(p/2),u=n.tiles[toID(c,f,p)];if(!u||!u.source)return null;if(a>1&&console.log("found parent tile z%d-%d-%d",c,f,p),isClippedSquare(u,i,o.buffer))return transform.tile(u,i);a>1&&console.time("drilling down");var h=this.splitTile(u.source,c,f,p,e,t,r);if(a>1&&console.timeEnd("drilling down"),null!==h){var m=1<<e-h;l=toID(h,Math.floor(t/m),Math.floor(r/m))}return this.tiles[l]?transform.tile(this.tiles[l],i):null};var identity=function(e){return e},transform$3=function(e){if(null==(t=e.transform))return identity;var t,r,n,o=t.scale[0],i=t.scale[1],a=t.translate[0],s=t.translate[1];return function(e,t){return t||(r=n=0),e[0]=(r+=e[0])*o+a,e[1]=(n+=e[1])*i+s,e}},bbox=function(e){function t(e){s[0]=e[0],s[1]=e[1],a(s),s[0]<l&&(l=s[0]),s[0]>c&&(c=s[0]),s[1]<u&&(u=s[1]),s[1]>f&&(f=s[1])}function r(e){switch(e.type){case"GeometryCollection":e.geometries.forEach(r);break;case"Point":t(e.coordinates);break;case"MultiPoint":e.coordinates.forEach(t)}}var n=e.bbox;if(!n){var o,i,a=transform$3(e),s=new Array(2),l=1/0,u=l,c=-l,f=-l;e.arcs.forEach(function(e){for(var t=-1,r=e.length;++t<r;)o=e[t],s[0]=o[0],s[1]=o[1],a(s,t),s[0]<l&&(l=s[0]),s[0]>c&&(c=s[0]),s[1]<u&&(u=s[1]),s[1]>f&&(f=s[1])});for(i in e.objects)r(e.objects[i]);n=e.bbox=[l,u,c,f]}return n},reverse=function(e,t){for(var r,n=e.length,o=n-t;o<--n;)r=e[o],e[o++]=e[n],e[n]=r},feature=function(e,t){return"GeometryCollection"===t.type?{type:"FeatureCollection",features:t.geometries.map(function(t){return feature$1(e,t)})}:feature$1(e,t)},stitch=function(e,t){function r(t){var r,n=e.arcs[t<0?~t:t],o=n[0];return e.transform?(r=[0,0],n.forEach(function(e){r[0]+=e[0],r[1]+=e[1]})):r=n[n.length-1],t<0?[r,o]:[o,r]}function n(e,t){for(var r in e){var n=e[r];delete t[n.start],delete n.start,delete n.end,n.forEach(function(e){o[e<0?~e:e]=1}),s.push(n)}}var o={},i={},a={},s=[],l=-1;return t.forEach(function(r,n){var o,i=e.arcs[r<0?~r:r];i.length<3&&!i[1][0]&&!i[1][1]&&(o=t[++l],t[l]=r,t[n]=o)}),t.forEach(function(e){var t,n,o=r(e),s=o[0],l=o[1];if(t=a[s])if(delete a[t.end],t.push(e),t.end=l,n=i[l]){delete i[n.start];var u=n===t?t:t.concat(n);i[u.start=t.start]=a[u.end=n.end]=u}else i[t.start]=a[t.end]=t;else if(t=i[l])if(delete i[t.start],t.unshift(e),t.start=s,n=a[s]){delete a[n.end];var c=n===t?t:n.concat(t);i[c.start=n.start]=a[c.end=t.end]=c}else i[t.start]=a[t.end]=t;else t=[e],i[t.start=s]=a[t.end=l]=t}),n(a,i),n(i,a),t.forEach(function(e){o[e<0?~e:e]||s.push([e])}),s},bisect=function(e,t){for(var r=0,n=e.length;r<n;){var o=r+n>>>1;e[o]<t?r=o+1:n=o}return r},slicers={},options;onmessage=function(e){if("slice"===e.data[0]){var t=e.data[1];if(options=e.data[2],t.type&&"Topology"===t.type)for(var r in t.objects)slicers[r]=index(feature(t,t.objects[r]),options);else slicers[options.vectorTileLayerName]=index(t,options)}else if("get"===e.data[0]){var n=e.data[1],o={};for(var r in slicers){var i=slicers[r].getTile(n.z,n.x,n.y);if(i){var a={features:[],extent:options.extent,name:options.vectorTileLayerName,length:i.features.length};for(var s in i.features){var l={geometry:i.features[s].geometry,properties:i.features[s].tags,type:i.features[s].type};a.features.push(l)}o[r]=a}}postMessage({layers:o,coords:n})}};\n', "text/plain; charset=us-ascii", false);
  L.VectorGrid.Slicer = L.VectorGrid.extend({ options: { vectorTileLayerName: "sliced", extent: 4096, maxZoom: 14 }, initialize: function(t, e) {
    L.VectorGrid.prototype.initialize.call(this, e);
    var e = {};
    for (var r in this.options)
      "rendererFactory" !== r && "vectorTileLayerStyles" !== r && "function" != typeof this.options[r] && (e[r] = this.options[r]);
    this._worker = new Worker(workerCode), this._worker.postMessage(["slice", t, e]);
  }, _getVectorTilePromise: function(t) {
    var e = this, r = new Promise(function(r2) {
      e._worker.addEventListener("message", function i2(n) {
        n.data.coords && n.data.coords.x === t.x && n.data.coords.y === t.y && n.data.coords.z === t.z && (r2(n.data), e._worker.removeEventListener("message", i2));
      });
    });
    return this._worker.postMessage(["get", t]), r;
  } }), L.vectorGrid.slicer = function(t, e) {
    return new L.VectorGrid.Slicer(t, e);
  }, L.Canvas.Tile = L.Canvas.extend({ initialize: function(t, e, r) {
    L.Canvas.prototype.initialize.call(this, r), this._tileCoord = t, this._size = e, this._initContainer(), this._container.setAttribute("width", this._size.x), this._container.setAttribute("height", this._size.y), this._layers = {}, this._drawnLayers = {}, this._drawing = true, r.interactive && (this._container.style.pointerEvents = "auto");
  }, getCoord: function() {
    return this._tileCoord;
  }, getContainer: function() {
    return this._container;
  }, getOffset: function() {
    return this._tileCoord.scaleBy(this._size).subtract(this._map.getPixelOrigin());
  }, onAdd: L.Util.falseFn, addTo: function(t) {
    this._map = t;
  }, removeFrom: function(t) {
    delete this._map;
  }, _onClick: function(t) {
    var e, r, i2 = this._map.mouseEventToLayerPoint(t).subtract(this.getOffset());
    for (var n in this._layers)
      e = this._layers[n], e.options.interactive && e._containsPoint(i2) && !this._map._draggableMoved(e) && (r = e);
    r && (L.DomEvent.fakeStop(t), this._fireEvent([r], t));
  }, _onMouseMove: function(t) {
    if (this._map && !this._map.dragging.moving() && !this._map._animatingZoom) {
      var e = this._map.mouseEventToLayerPoint(t).subtract(this.getOffset());
      this._handleMouseHover(t, e);
    }
  }, _updateIcon: function(t) {
    if (this._drawing) {
      var e = t.options.icon, r = e.options, i2 = L.point(r.iconSize), n = r.iconAnchor || i2 && i2.divideBy(2, true), o = t._point.subtract(n), s = this._ctx, a = t._getImage();
      a.complete ? s.drawImage(a, o.x, o.y, i2.x, i2.y) : L.DomEvent.on(a, "load", function() {
        s.drawImage(a, o.x, o.y, i2.x, i2.y);
      }), this._drawnLayers[t._leaflet_id] = t;
    }
  } }), L.canvas.tile = function(t, e, r) {
    return new L.Canvas.Tile(t, e, r);
  };

  // node_modules/pmtiles/dist/index.mjs
  var __async = (__this, __arguments, generator) => {
    return new Promise((resolve, reject) => {
      var fulfilled = (value) => {
        try {
          step(generator.next(value));
        } catch (e) {
          reject(e);
        }
      };
      var rejected = (value) => {
        try {
          step(generator.throw(value));
        } catch (e) {
          reject(e);
        }
      };
      var step = (x2) => x2.done ? resolve(x2.value) : Promise.resolve(x2.value).then(fulfilled, rejected);
      step((generator = generator.apply(__this, __arguments)).next());
    });
  };
  var u8 = Uint8Array;
  var u16 = Uint16Array;
  var u32 = Uint32Array;
  var fleb = new u8([0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 2, 2, 2, 2, 3, 3, 3, 3, 4, 4, 4, 4, 5, 5, 5, 5, 0, 0, 0, 0]);
  var fdeb = new u8([0, 0, 0, 0, 1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6, 7, 7, 8, 8, 9, 9, 10, 10, 11, 11, 12, 12, 13, 13, 0, 0]);
  var clim = new u8([16, 17, 18, 0, 8, 7, 9, 6, 10, 5, 11, 4, 12, 3, 13, 2, 14, 1, 15]);
  var freb = function(eb, start) {
    var b = new u16(31);
    for (var i2 = 0; i2 < 31; ++i2) {
      b[i2] = start += 1 << eb[i2 - 1];
    }
    var r = new u32(b[30]);
    for (var i2 = 1; i2 < 30; ++i2) {
      for (var j = b[i2]; j < b[i2 + 1]; ++j) {
        r[j] = j - b[i2] << 5 | i2;
      }
    }
    return [b, r];
  };
  var _a = freb(fleb, 2);
  var fl = _a[0];
  var revfl = _a[1];
  fl[28] = 258, revfl[258] = 28;
  var _b = freb(fdeb, 0);
  var fd = _b[0];
  var revfd = _b[1];
  var rev = new u16(32768);
  for (i = 0; i < 32768; ++i) {
    x = (i & 43690) >>> 1 | (i & 21845) << 1;
    x = (x & 52428) >>> 2 | (x & 13107) << 2;
    x = (x & 61680) >>> 4 | (x & 3855) << 4;
    rev[i] = ((x & 65280) >>> 8 | (x & 255) << 8) >>> 1;
  }
  var x;
  var i;
  var hMap = function(cd, mb, r) {
    var s = cd.length;
    var i2 = 0;
    var l = new u16(mb);
    for (; i2 < s; ++i2) {
      if (cd[i2])
        ++l[cd[i2] - 1];
    }
    var le = new u16(mb);
    for (i2 = 0; i2 < mb; ++i2) {
      le[i2] = le[i2 - 1] + l[i2 - 1] << 1;
    }
    var co;
    if (r) {
      co = new u16(1 << mb);
      var rvb = 15 - mb;
      for (i2 = 0; i2 < s; ++i2) {
        if (cd[i2]) {
          var sv = i2 << 4 | cd[i2];
          var r_1 = mb - cd[i2];
          var v = le[cd[i2] - 1]++ << r_1;
          for (var m = v | (1 << r_1) - 1; v <= m; ++v) {
            co[rev[v] >>> rvb] = sv;
          }
        }
      }
    } else {
      co = new u16(s);
      for (i2 = 0; i2 < s; ++i2) {
        if (cd[i2]) {
          co[i2] = rev[le[cd[i2] - 1]++] >>> 15 - cd[i2];
        }
      }
    }
    return co;
  };
  var flt = new u8(288);
  for (i = 0; i < 144; ++i)
    flt[i] = 8;
  var i;
  for (i = 144; i < 256; ++i)
    flt[i] = 9;
  var i;
  for (i = 256; i < 280; ++i)
    flt[i] = 7;
  var i;
  for (i = 280; i < 288; ++i)
    flt[i] = 8;
  var i;
  var fdt = new u8(32);
  for (i = 0; i < 32; ++i)
    fdt[i] = 5;
  var i;
  var flrm = /* @__PURE__ */ hMap(flt, 9, 1);
  var fdrm = /* @__PURE__ */ hMap(fdt, 5, 1);
  var max = function(a) {
    var m = a[0];
    for (var i2 = 1; i2 < a.length; ++i2) {
      if (a[i2] > m)
        m = a[i2];
    }
    return m;
  };
  var bits = function(d, p, m) {
    var o = p / 8 | 0;
    return (d[o] | d[o + 1] << 8) >> (p & 7) & m;
  };
  var bits16 = function(d, p) {
    var o = p / 8 | 0;
    return (d[o] | d[o + 1] << 8 | d[o + 2] << 16) >> (p & 7);
  };
  var shft = function(p) {
    return (p + 7) / 8 | 0;
  };
  var slc = function(v, s, e) {
    if (s == null || s < 0)
      s = 0;
    if (e == null || e > v.length)
      e = v.length;
    var n = new (v.BYTES_PER_ELEMENT == 2 ? u16 : v.BYTES_PER_ELEMENT == 4 ? u32 : u8)(e - s);
    n.set(v.subarray(s, e));
    return n;
  };
  var ec = [
    "unexpected EOF",
    "invalid block type",
    "invalid length/literal",
    "invalid distance",
    "stream finished",
    "no stream handler",
    ,
    "no callback",
    "invalid UTF-8 data",
    "extra field too long",
    "date not in range 1980-2099",
    "filename too long",
    "stream finishing",
    "invalid zip data"
  ];
  var err = function(ind, msg, nt) {
    var e = new Error(msg || ec[ind]);
    e.code = ind;
    if (Error.captureStackTrace)
      Error.captureStackTrace(e, err);
    if (!nt)
      throw e;
    return e;
  };
  var inflt = function(dat, buf, st) {
    var sl = dat.length;
    if (!sl || st && st.f && !st.l)
      return buf || new u8(0);
    var noBuf = !buf || st;
    var noSt = !st || st.i;
    if (!st)
      st = {};
    if (!buf)
      buf = new u8(sl * 3);
    var cbuf = function(l2) {
      var bl = buf.length;
      if (l2 > bl) {
        var nbuf = new u8(Math.max(bl * 2, l2));
        nbuf.set(buf);
        buf = nbuf;
      }
    };
    var final = st.f || 0, pos = st.p || 0, bt = st.b || 0, lm = st.l, dm = st.d, lbt = st.m, dbt = st.n;
    var tbts = sl * 8;
    do {
      if (!lm) {
        final = bits(dat, pos, 1);
        var type = bits(dat, pos + 1, 3);
        pos += 3;
        if (!type) {
          var s = shft(pos) + 4, l = dat[s - 4] | dat[s - 3] << 8, t = s + l;
          if (t > sl) {
            if (noSt)
              err(0);
            break;
          }
          if (noBuf)
            cbuf(bt + l);
          buf.set(dat.subarray(s, t), bt);
          st.b = bt += l, st.p = pos = t * 8, st.f = final;
          continue;
        } else if (type == 1)
          lm = flrm, dm = fdrm, lbt = 9, dbt = 5;
        else if (type == 2) {
          var hLit = bits(dat, pos, 31) + 257, hcLen = bits(dat, pos + 10, 15) + 4;
          var tl = hLit + bits(dat, pos + 5, 31) + 1;
          pos += 14;
          var ldt = new u8(tl);
          var clt = new u8(19);
          for (var i2 = 0; i2 < hcLen; ++i2) {
            clt[clim[i2]] = bits(dat, pos + i2 * 3, 7);
          }
          pos += hcLen * 3;
          var clb = max(clt), clbmsk = (1 << clb) - 1;
          var clm = hMap(clt, clb, 1);
          for (var i2 = 0; i2 < tl; ) {
            var r = clm[bits(dat, pos, clbmsk)];
            pos += r & 15;
            var s = r >>> 4;
            if (s < 16) {
              ldt[i2++] = s;
            } else {
              var c = 0, n = 0;
              if (s == 16)
                n = 3 + bits(dat, pos, 3), pos += 2, c = ldt[i2 - 1];
              else if (s == 17)
                n = 3 + bits(dat, pos, 7), pos += 3;
              else if (s == 18)
                n = 11 + bits(dat, pos, 127), pos += 7;
              while (n--)
                ldt[i2++] = c;
            }
          }
          var lt = ldt.subarray(0, hLit), dt = ldt.subarray(hLit);
          lbt = max(lt);
          dbt = max(dt);
          lm = hMap(lt, lbt, 1);
          dm = hMap(dt, dbt, 1);
        } else
          err(1);
        if (pos > tbts) {
          if (noSt)
            err(0);
          break;
        }
      }
      if (noBuf)
        cbuf(bt + 131072);
      var lms = (1 << lbt) - 1, dms = (1 << dbt) - 1;
      var lpos = pos;
      for (; ; lpos = pos) {
        var c = lm[bits16(dat, pos) & lms], sym = c >>> 4;
        pos += c & 15;
        if (pos > tbts) {
          if (noSt)
            err(0);
          break;
        }
        if (!c)
          err(2);
        if (sym < 256)
          buf[bt++] = sym;
        else if (sym == 256) {
          lpos = pos, lm = null;
          break;
        } else {
          var add = sym - 254;
          if (sym > 264) {
            var i2 = sym - 257, b = fleb[i2];
            add = bits(dat, pos, (1 << b) - 1) + fl[i2];
            pos += b;
          }
          var d = dm[bits16(dat, pos) & dms], dsym = d >>> 4;
          if (!d)
            err(3);
          pos += d & 15;
          var dt = fd[dsym];
          if (dsym > 3) {
            var b = fdeb[dsym];
            dt += bits16(dat, pos) & (1 << b) - 1, pos += b;
          }
          if (pos > tbts) {
            if (noSt)
              err(0);
            break;
          }
          if (noBuf)
            cbuf(bt + 131072);
          var end = bt + add;
          for (; bt < end; bt += 4) {
            buf[bt] = buf[bt - dt];
            buf[bt + 1] = buf[bt + 1 - dt];
            buf[bt + 2] = buf[bt + 2 - dt];
            buf[bt + 3] = buf[bt + 3 - dt];
          }
          bt = end;
        }
      }
      st.l = lm, st.p = lpos, st.b = bt, st.f = final;
      if (lm)
        final = 1, st.m = lbt, st.d = dm, st.n = dbt;
    } while (!final);
    return bt == buf.length ? buf : slc(buf, 0, bt);
  };
  var et = /* @__PURE__ */ new u8(0);
  var gzs = function(d) {
    if (d[0] != 31 || d[1] != 139 || d[2] != 8)
      err(6, "invalid gzip data");
    var flg = d[3];
    var st = 10;
    if (flg & 4)
      st += d[10] | (d[11] << 8) + 2;
    for (var zs = (flg >> 3 & 1) + (flg >> 4 & 1); zs > 0; zs -= !d[st++])
      ;
    return st + (flg & 2);
  };
  var gzl = function(d) {
    var l = d.length;
    return (d[l - 4] | d[l - 3] << 8 | d[l - 2] << 16 | d[l - 1] << 24) >>> 0;
  };
  var zlv = function(d) {
    if ((d[0] & 15) != 8 || d[0] >>> 4 > 7 || (d[0] << 8 | d[1]) % 31)
      err(6, "invalid zlib data");
    if (d[1] & 32)
      err(6, "invalid zlib data: preset dictionaries not supported");
  };
  function inflateSync(data, out) {
    return inflt(data, out);
  }
  function gunzipSync(data, out) {
    return inflt(data.subarray(gzs(data), -8), out || new u8(gzl(data)));
  }
  function unzlibSync(data, out) {
    return inflt((zlv(data), data.subarray(2, -4)), out);
  }
  function decompressSync(data, out) {
    return data[0] == 31 && data[1] == 139 && data[2] == 8 ? gunzipSync(data, out) : (data[0] & 15) != 8 || data[0] >> 4 > 7 || (data[0] << 8 | data[1]) % 31 ? inflateSync(data, out) : unzlibSync(data, out);
  }
  var td = typeof TextDecoder != "undefined" && /* @__PURE__ */ new TextDecoder();
  var tds = 0;
  try {
    td.decode(et, { stream: true });
    tds = 1;
  } catch (e) {
  }
  var shift = (n, shift2) => {
    return n * Math.pow(2, shift2);
  };
  var unshift = (n, shift2) => {
    return Math.floor(n / Math.pow(2, shift2));
  };
  var getUint24 = (view, pos) => {
    return shift(view.getUint16(pos + 1, true), 8) + view.getUint8(pos);
  };
  var getUint48 = (view, pos) => {
    return shift(view.getUint32(pos + 2, true), 16) + view.getUint16(pos, true);
  };
  var compare = (tz, tx, ty, view, i2) => {
    if (tz != view.getUint8(i2))
      return tz - view.getUint8(i2);
    const x2 = getUint24(view, i2 + 1);
    if (tx != x2)
      return tx - x2;
    const y = getUint24(view, i2 + 4);
    if (ty != y)
      return ty - y;
    return 0;
  };
  var queryLeafdir = (view, z, x2, y) => {
    const offset_len = queryView(view, z | 128, x2, y);
    if (offset_len) {
      return {
        z,
        x: x2,
        y,
        offset: offset_len[0],
        length: offset_len[1],
        is_dir: true
      };
    }
    return null;
  };
  var queryTile = (view, z, x2, y) => {
    const offset_len = queryView(view, z, x2, y);
    if (offset_len) {
      return {
        z,
        x: x2,
        y,
        offset: offset_len[0],
        length: offset_len[1],
        is_dir: false
      };
    }
    return null;
  };
  var queryView = (view, z, x2, y) => {
    let m = 0;
    let n = view.byteLength / 17 - 1;
    while (m <= n) {
      const k = n + m >> 1;
      const cmp = compare(z, x2, y, view, k * 17);
      if (cmp > 0) {
        m = k + 1;
      } else if (cmp < 0) {
        n = k - 1;
      } else {
        return [getUint48(view, k * 17 + 7), view.getUint32(k * 17 + 13, true)];
      }
    }
    return null;
  };
  var entrySort = (a, b) => {
    if (a.is_dir && !b.is_dir) {
      return 1;
    }
    if (!a.is_dir && b.is_dir) {
      return -1;
    }
    if (a.z !== b.z) {
      return a.z - b.z;
    }
    if (a.x !== b.x) {
      return a.x - b.x;
    }
    return a.y - b.y;
  };
  var parseEntry = (dataview, i2) => {
    const z_raw = dataview.getUint8(i2 * 17);
    const z = z_raw & 127;
    return {
      z,
      x: getUint24(dataview, i2 * 17 + 1),
      y: getUint24(dataview, i2 * 17 + 4),
      offset: getUint48(dataview, i2 * 17 + 7),
      length: dataview.getUint32(i2 * 17 + 13, true),
      is_dir: z_raw >> 7 === 1
    };
  };
  var sortDir = (a) => {
    const entries = [];
    const view = new DataView(a);
    for (let i2 = 0; i2 < view.byteLength / 17; i2++) {
      entries.push(parseEntry(view, i2));
    }
    return createDirectory(entries);
  };
  var createDirectory = (entries) => {
    entries.sort(entrySort);
    const buffer = new ArrayBuffer(17 * entries.length);
    const arr = new Uint8Array(buffer);
    for (let i2 = 0; i2 < entries.length; i2++) {
      const entry = entries[i2];
      let z = entry.z;
      if (entry.is_dir)
        z = z | 128;
      arr[i2 * 17] = z;
      arr[i2 * 17 + 1] = entry.x & 255;
      arr[i2 * 17 + 2] = entry.x >> 8 & 255;
      arr[i2 * 17 + 3] = entry.x >> 16 & 255;
      arr[i2 * 17 + 4] = entry.y & 255;
      arr[i2 * 17 + 5] = entry.y >> 8 & 255;
      arr[i2 * 17 + 6] = entry.y >> 16 & 255;
      arr[i2 * 17 + 7] = entry.offset & 255;
      arr[i2 * 17 + 8] = unshift(entry.offset, 8) & 255;
      arr[i2 * 17 + 9] = unshift(entry.offset, 16) & 255;
      arr[i2 * 17 + 10] = unshift(entry.offset, 24) & 255;
      arr[i2 * 17 + 11] = unshift(entry.offset, 32) & 255;
      arr[i2 * 17 + 12] = unshift(entry.offset, 48) & 255;
      arr[i2 * 17 + 13] = entry.length & 255;
      arr[i2 * 17 + 14] = entry.length >> 8 & 255;
      arr[i2 * 17 + 15] = entry.length >> 16 & 255;
      arr[i2 * 17 + 16] = entry.length >> 24 & 255;
    }
    return buffer;
  };
  var deriveLeaf = (view, tile) => {
    if (view.byteLength < 17)
      return null;
    const numEntries = view.byteLength / 17;
    const entry = parseEntry(view, numEntries - 1);
    if (entry.is_dir) {
      const leaf_level = entry.z;
      const level_diff = tile.z - leaf_level;
      const leaf_x = Math.trunc(tile.x / (1 << level_diff));
      const leaf_y = Math.trunc(tile.y / (1 << level_diff));
      return { z: leaf_level, x: leaf_x, y: leaf_y };
    }
    return null;
  };
  function getHeader(source) {
    return __async(this, null, function* () {
      const resp = yield source.getBytes(0, 512e3);
      const dataview = new DataView(resp.data);
      const json_size = dataview.getUint32(4, true);
      const root_entries = dataview.getUint16(8, true);
      const dec = new TextDecoder("utf-8");
      const json_metadata = JSON.parse(
        dec.decode(new DataView(resp.data, 10, json_size))
      );
      let tile_compression = 0;
      if (json_metadata.compression === "gzip") {
        tile_compression = 2;
      }
      let minzoom = 0;
      if ("minzoom" in json_metadata) {
        minzoom = +json_metadata.minzoom;
      }
      let maxzoom = 0;
      if ("maxzoom" in json_metadata) {
        maxzoom = +json_metadata.maxzoom;
      }
      let center_lon = 0;
      let center_lat = 0;
      let center_zoom = 0;
      let min_lon = -180;
      let min_lat = -85;
      let max_lon = 180;
      let max_lat = 85;
      if (json_metadata.bounds) {
        const split = json_metadata.bounds.split(",");
        min_lon = +split[0];
        min_lat = +split[1];
        max_lon = +split[2];
        max_lat = +split[3];
      }
      if (json_metadata.center) {
        const split = json_metadata.center.split(",");
        center_lon = +split[0];
        center_lat = +split[1];
        center_zoom = +split[2];
      }
      const header = {
        specVersion: dataview.getUint16(2, true),
        rootDirectoryOffset: 10 + json_size,
        rootDirectoryLength: root_entries * 17,
        jsonMetadataOffset: 10,
        jsonMetadataLength: json_size,
        leafDirectoryOffset: 0,
        leafDirectoryLength: void 0,
        tileDataOffset: 0,
        tileDataLength: void 0,
        numAddressedTiles: 0,
        numTileEntries: 0,
        numTileContents: 0,
        clustered: false,
        internalCompression: 1,
        tileCompression: tile_compression,
        tileType: 1,
        minZoom: minzoom,
        maxZoom: maxzoom,
        minLon: min_lon,
        minLat: min_lat,
        maxLon: max_lon,
        maxLat: max_lat,
        centerZoom: center_zoom,
        centerLon: center_lon,
        centerLat: center_lat,
        etag: resp.etag
      };
      return header;
    });
  }
  function getZxy(header, source, cache, z, x2, y, signal) {
    return __async(this, null, function* () {
      let root_dir = yield cache.getArrayBuffer(
        source,
        header.rootDirectoryOffset,
        header.rootDirectoryLength,
        header
      );
      if (header.specVersion === 1) {
        root_dir = sortDir(root_dir);
      }
      const entry = queryTile(new DataView(root_dir), z, x2, y);
      if (entry) {
        const resp = yield source.getBytes(entry.offset, entry.length, signal);
        let tile_data = resp.data;
        const view = new DataView(tile_data);
        if (view.getUint8(0) == 31 && view.getUint8(1) == 139) {
          tile_data = decompressSync(new Uint8Array(tile_data));
        }
        return {
          data: tile_data
        };
      }
      const leafcoords = deriveLeaf(new DataView(root_dir), { z, x: x2, y });
      if (leafcoords) {
        const leafdir_entry = queryLeafdir(
          new DataView(root_dir),
          leafcoords.z,
          leafcoords.x,
          leafcoords.y
        );
        if (leafdir_entry) {
          let leaf_dir = yield cache.getArrayBuffer(
            source,
            leafdir_entry.offset,
            leafdir_entry.length,
            header
          );
          if (header.specVersion === 1) {
            leaf_dir = sortDir(leaf_dir);
          }
          const tile_entry = queryTile(new DataView(leaf_dir), z, x2, y);
          if (tile_entry) {
            const resp = yield source.getBytes(
              tile_entry.offset,
              tile_entry.length,
              signal
            );
            let tile_data = resp.data;
            const view = new DataView(tile_data);
            if (view.getUint8(0) == 31 && view.getUint8(1) == 139) {
              tile_data = decompressSync(new Uint8Array(tile_data));
            }
            return {
              data: tile_data
            };
          }
        }
      }
      return void 0;
    });
  }
  var v2_default = {
    getHeader,
    getZxy
  };
  function toNum2(low, high) {
    return (high >>> 0) * 4294967296 + (low >>> 0);
  }
  function readVarintRemainder2(l, p) {
    const buf = p.buf;
    let h, b;
    b = buf[p.pos++];
    h = (b & 112) >> 4;
    if (b < 128)
      return toNum2(l, h);
    b = buf[p.pos++];
    h |= (b & 127) << 3;
    if (b < 128)
      return toNum2(l, h);
    b = buf[p.pos++];
    h |= (b & 127) << 10;
    if (b < 128)
      return toNum2(l, h);
    b = buf[p.pos++];
    h |= (b & 127) << 17;
    if (b < 128)
      return toNum2(l, h);
    b = buf[p.pos++];
    h |= (b & 127) << 24;
    if (b < 128)
      return toNum2(l, h);
    b = buf[p.pos++];
    h |= (b & 1) << 31;
    if (b < 128)
      return toNum2(l, h);
    throw new Error("Expected varint not more than 10 bytes");
  }
  function readVarint(p) {
    const buf = p.buf;
    let val, b;
    b = buf[p.pos++];
    val = b & 127;
    if (b < 128)
      return val;
    b = buf[p.pos++];
    val |= (b & 127) << 7;
    if (b < 128)
      return val;
    b = buf[p.pos++];
    val |= (b & 127) << 14;
    if (b < 128)
      return val;
    b = buf[p.pos++];
    val |= (b & 127) << 21;
    if (b < 128)
      return val;
    b = buf[p.pos];
    val |= (b & 15) << 28;
    return readVarintRemainder2(val, p);
  }
  function rotate(n, xy, rx, ry) {
    if (ry == 0) {
      if (rx == 1) {
        xy[0] = n - 1 - xy[0];
        xy[1] = n - 1 - xy[1];
      }
      const t = xy[0];
      xy[0] = xy[1];
      xy[1] = t;
    }
  }
  function zxyToTileId(z, x2, y) {
    if (z > 26) {
      throw Error("Tile zoom level exceeds max safe number limit (26)");
    }
    if (x2 > Math.pow(2, z) - 1 || y > Math.pow(2, z) - 1) {
      throw Error("tile x/y outside zoom level bounds");
    }
    let acc = 0;
    let tz = 0;
    while (tz < z) {
      acc += Math.pow(2, tz) * Math.pow(2, tz);
      tz++;
    }
    const n = Math.pow(2, z);
    let rx = 0;
    let ry = 0;
    let d = 0;
    const xy = [x2, y];
    let s = n / 2;
    while (s > 0) {
      rx = (xy[0] & s) > 0 ? 1 : 0;
      ry = (xy[1] & s) > 0 ? 1 : 0;
      d += s * s * (3 * rx ^ ry);
      rotate(s, xy, rx, ry);
      s = s / 2;
    }
    return acc + d;
  }
  function fflateDecompress(buf, compression) {
    return __async(this, null, function* () {
      if (compression === 1 || compression === 0) {
        return buf;
      } else if (compression === 2) {
        return decompressSync(new Uint8Array(buf));
      } else {
        throw Error("Compression method not supported");
      }
    });
  }
  var HEADER_SIZE_BYTES = 127;
  function findTile(entries, tileId) {
    let m = 0;
    let n = entries.length - 1;
    while (m <= n) {
      const k = n + m >> 1;
      const cmp = tileId - entries[k].tileId;
      if (cmp > 0) {
        m = k + 1;
      } else if (cmp < 0) {
        n = k - 1;
      } else {
        return entries[k];
      }
    }
    if (n >= 0) {
      if (entries[n].runLength === 0) {
        return entries[n];
      }
      if (tileId - entries[n].tileId < entries[n].runLength) {
        return entries[n];
      }
    }
    return null;
  }
  var FetchSource = class {
    constructor(url) {
      this.url = url;
    }
    getKey() {
      return this.url;
    }
    getBytes(offset, length, signal) {
      return __async(this, null, function* () {
        let controller;
        if (!signal) {
          controller = new AbortController();
          signal = controller.signal;
        }
        let resp = yield fetch(this.url, {
          signal,
          headers: { Range: "bytes=" + offset + "-" + (offset + length - 1) }
        });
        if (resp.status === 416 && offset === 0) {
          const content_range = resp.headers.get("Content-Range");
          if (!content_range || !content_range.startsWith("bytes */")) {
            throw Error("Missing content-length on 416 response");
          }
          const actual_length = +content_range.substr(8);
          resp = yield fetch(this.url, {
            signal,
            headers: { Range: "bytes=0-" + (actual_length - 1) }
          });
        }
        if (resp.status >= 300) {
          throw Error("Bad response code: " + resp.status);
        }
        const content_length = resp.headers.get("Content-Length");
        if (resp.status === 200 && (!content_length || +content_length > length)) {
          if (controller)
            controller.abort();
          throw Error(
            "Server returned no content-length header or content-length exceeding request. Check that your storage backend supports HTTP Byte Serving."
          );
        }
        const a = yield resp.arrayBuffer();
        return {
          data: a,
          etag: resp.headers.get("ETag") || void 0,
          cacheControl: resp.headers.get("Cache-Control") || void 0,
          expires: resp.headers.get("Expires") || void 0
        };
      });
    }
  };
  function getUint64(v, offset) {
    const wh = v.getUint32(offset + 4, true);
    const wl = v.getUint32(offset + 0, true);
    return wh * Math.pow(2, 32) + wl;
  }
  function bytesToHeader(bytes, etag) {
    const v = new DataView(bytes);
    const spec_version = v.getUint8(7);
    if (spec_version > 3) {
      throw Error(
        `Archive is spec version ${spec_version} but this library supports up to spec version 3`
      );
    }
    return {
      specVersion: spec_version,
      rootDirectoryOffset: getUint64(v, 8),
      rootDirectoryLength: getUint64(v, 16),
      jsonMetadataOffset: getUint64(v, 24),
      jsonMetadataLength: getUint64(v, 32),
      leafDirectoryOffset: getUint64(v, 40),
      leafDirectoryLength: getUint64(v, 48),
      tileDataOffset: getUint64(v, 56),
      tileDataLength: getUint64(v, 64),
      numAddressedTiles: getUint64(v, 72),
      numTileEntries: getUint64(v, 80),
      numTileContents: getUint64(v, 88),
      clustered: v.getUint8(96) === 1,
      internalCompression: v.getUint8(97),
      tileCompression: v.getUint8(98),
      tileType: v.getUint8(99),
      minZoom: v.getUint8(100),
      maxZoom: v.getUint8(101),
      minLon: v.getInt32(102, true) / 1e7,
      minLat: v.getInt32(106, true) / 1e7,
      maxLon: v.getInt32(110, true) / 1e7,
      maxLat: v.getInt32(114, true) / 1e7,
      centerZoom: v.getUint8(118),
      centerLon: v.getInt32(119, true) / 1e7,
      centerLat: v.getInt32(123, true) / 1e7,
      etag
    };
  }
  function deserializeIndex(buffer) {
    const p = { buf: new Uint8Array(buffer), pos: 0 };
    const numEntries = readVarint(p);
    const entries = [];
    let lastId = 0;
    for (let i2 = 0; i2 < numEntries; i2++) {
      const v = readVarint(p);
      entries.push({ tileId: lastId + v, offset: 0, length: 0, runLength: 1 });
      lastId += v;
    }
    for (let i2 = 0; i2 < numEntries; i2++) {
      entries[i2].runLength = readVarint(p);
    }
    for (let i2 = 0; i2 < numEntries; i2++) {
      entries[i2].length = readVarint(p);
    }
    for (let i2 = 0; i2 < numEntries; i2++) {
      const v = readVarint(p);
      if (v === 0 && i2 > 0) {
        entries[i2].offset = entries[i2 - 1].offset + entries[i2 - 1].length;
      } else {
        entries[i2].offset = v - 1;
      }
    }
    return entries;
  }
  function detectVersion(a) {
    const v = new DataView(a);
    if (v.getUint16(2, true) === 2) {
      console.warn(
        "PMTiles spec version 2 has been deprecated; please see github.com/protomaps/PMTiles for tools to upgrade"
      );
      return 2;
    } else if (v.getUint16(2, true) === 1) {
      console.warn(
        "PMTiles spec version 1 has been deprecated; please see github.com/protomaps/PMTiles for tools to upgrade"
      );
      return 1;
    }
    return 3;
  }
  var EtagMismatch = class extends Error {
  };
  function getHeaderAndRoot(source, decompress, prefetch, current_etag) {
    return __async(this, null, function* () {
      const resp = yield source.getBytes(0, 16384);
      const v = new DataView(resp.data);
      if (v.getUint16(0, true) !== 19792) {
        throw new Error("Wrong magic number for PMTiles archive");
      }
      if (detectVersion(resp.data) < 3) {
        return [yield v2_default.getHeader(source)];
      }
      const headerData = resp.data.slice(0, HEADER_SIZE_BYTES);
      let resp_etag = resp.etag;
      if (current_etag && resp.etag != current_etag) {
        console.warn(
          "ETag conflict detected; your HTTP server might not support content-based ETag headers. ETags disabled for " + source.getKey()
        );
        resp_etag = void 0;
      }
      const header = bytesToHeader(headerData, resp_etag);
      if (prefetch) {
        const rootDirData = resp.data.slice(
          header.rootDirectoryOffset,
          header.rootDirectoryOffset + header.rootDirectoryLength
        );
        const dirKey = source.getKey() + "|" + (header.etag || "") + "|" + header.rootDirectoryOffset + "|" + header.rootDirectoryLength;
        const rootDir = deserializeIndex(
          yield decompress(rootDirData, header.internalCompression)
        );
        return [header, [dirKey, rootDir.length, rootDir]];
      }
      return [header, void 0];
    });
  }
  function getDirectory(source, decompress, offset, length, header) {
    return __async(this, null, function* () {
      const resp = yield source.getBytes(offset, length);
      if (header.etag && header.etag !== resp.etag) {
        throw new EtagMismatch(resp.etag);
      }
      const data = yield decompress(resp.data, header.internalCompression);
      const directory = deserializeIndex(data);
      if (directory.length === 0) {
        throw new Error("Empty directory is invalid");
      }
      return directory;
    });
  }
  var SharedPromiseCache = class {
    constructor(maxCacheEntries = 100, prefetch = true, decompress = fflateDecompress) {
      this.cache = /* @__PURE__ */ new Map();
      this.maxCacheEntries = maxCacheEntries;
      this.counter = 1;
      this.prefetch = prefetch;
      this.decompress = decompress;
    }
    getHeader(source, current_etag) {
      return __async(this, null, function* () {
        const cacheKey = source.getKey();
        if (this.cache.has(cacheKey)) {
          this.cache.get(cacheKey).lastUsed = this.counter++;
          const data = yield this.cache.get(cacheKey).data;
          return data;
        }
        const p = new Promise((resolve, reject) => {
          getHeaderAndRoot(source, this.decompress, this.prefetch, current_etag).then((res) => {
            if (res[1]) {
              this.cache.set(res[1][0], {
                lastUsed: this.counter++,
                data: Promise.resolve(res[1][2])
              });
            }
            resolve(res[0]);
            this.prune();
          }).catch((e) => {
            reject(e);
          });
        });
        this.cache.set(cacheKey, { lastUsed: this.counter++, data: p });
        return p;
      });
    }
    getDirectory(source, offset, length, header) {
      return __async(this, null, function* () {
        const cacheKey = source.getKey() + "|" + (header.etag || "") + "|" + offset + "|" + length;
        if (this.cache.has(cacheKey)) {
          this.cache.get(cacheKey).lastUsed = this.counter++;
          const data = yield this.cache.get(cacheKey).data;
          return data;
        }
        const p = new Promise((resolve, reject) => {
          getDirectory(source, this.decompress, offset, length, header).then((directory) => {
            resolve(directory);
            this.prune();
          }).catch((e) => {
            reject(e);
          });
        });
        this.cache.set(cacheKey, { lastUsed: this.counter++, data: p });
        return p;
      });
    }
    getArrayBuffer(source, offset, length, header) {
      return __async(this, null, function* () {
        const cacheKey = source.getKey() + "|" + (header.etag || "") + "|" + offset + "|" + length;
        if (this.cache.has(cacheKey)) {
          this.cache.get(cacheKey).lastUsed = this.counter++;
          const data = yield this.cache.get(cacheKey).data;
          return data;
        }
        const p = new Promise((resolve, reject) => {
          source.getBytes(offset, length).then((resp) => {
            if (header.etag && header.etag !== resp.etag) {
              throw new EtagMismatch(resp.etag);
            }
            resolve(resp.data);
            if (this.cache.has(cacheKey)) {
            }
            this.prune();
          }).catch((e) => {
            reject(e);
          });
        });
        this.cache.set(cacheKey, { lastUsed: this.counter++, data: p });
        return p;
      });
    }
    prune() {
      if (this.cache.size >= this.maxCacheEntries) {
        let minUsed = Infinity;
        let minKey = void 0;
        this.cache.forEach(
          (cache_value, key) => {
            if (cache_value.lastUsed < minUsed) {
              minUsed = cache_value.lastUsed;
              minKey = key;
            }
          }
        );
        if (minKey) {
          this.cache.delete(minKey);
        }
      }
    }
    invalidate(source, current_etag) {
      return __async(this, null, function* () {
        this.cache.delete(source.getKey());
        yield this.getHeader(source, current_etag);
      });
    }
  };
  var PMTiles = class {
    constructor(source, cache, decompress) {
      if (typeof source === "string") {
        this.source = new FetchSource(source);
      } else {
        this.source = source;
      }
      if (decompress) {
        this.decompress = decompress;
      } else {
        this.decompress = fflateDecompress;
      }
      if (cache) {
        this.cache = cache;
      } else {
        this.cache = new SharedPromiseCache();
      }
    }
    getHeader() {
      return __async(this, null, function* () {
        return yield this.cache.getHeader(this.source);
      });
    }
    getZxyAttempt(z, x2, y, signal) {
      return __async(this, null, function* () {
        const tile_id = zxyToTileId(z, x2, y);
        const header = yield this.cache.getHeader(this.source);
        if (header.specVersion < 3) {
          return v2_default.getZxy(header, this.source, this.cache, z, x2, y, signal);
        }
        if (z < header.minZoom || z > header.maxZoom) {
          return void 0;
        }
        let d_o = header.rootDirectoryOffset;
        let d_l = header.rootDirectoryLength;
        for (let depth = 0; depth <= 3; depth++) {
          const directory = yield this.cache.getDirectory(
            this.source,
            d_o,
            d_l,
            header
          );
          const entry = findTile(directory, tile_id);
          if (entry) {
            if (entry.runLength > 0) {
              const resp = yield this.source.getBytes(
                header.tileDataOffset + entry.offset,
                entry.length,
                signal
              );
              if (header.etag && header.etag !== resp.etag) {
                throw new EtagMismatch(resp.etag);
              }
              return {
                data: yield this.decompress(resp.data, header.tileCompression),
                cacheControl: resp.cacheControl,
                expires: resp.expires
              };
            } else {
              d_o = header.leafDirectoryOffset + entry.offset;
              d_l = entry.length;
            }
          } else {
            return void 0;
          }
        }
        throw Error("Maximum directory depth exceeded");
      });
    }
    getZxy(z, x2, y, signal) {
      return __async(this, null, function* () {
        try {
          return yield this.getZxyAttempt(z, x2, y, signal);
        } catch (e) {
          if (e instanceof EtagMismatch) {
            this.cache.invalidate(this.source, e.message);
            return yield this.getZxyAttempt(z, x2, y, signal);
          } else {
            throw e;
          }
        }
      });
    }
    getMetadataAttempt() {
      return __async(this, null, function* () {
        const header = yield this.cache.getHeader(this.source);
        const resp = yield this.source.getBytes(
          header.jsonMetadataOffset,
          header.jsonMetadataLength
        );
        if (header.etag && header.etag !== resp.etag) {
          throw new EtagMismatch(resp.etag);
        }
        const decompressed = yield this.decompress(
          resp.data,
          header.internalCompression
        );
        const dec = new TextDecoder("utf-8");
        return JSON.parse(dec.decode(decompressed));
      });
    }
    getMetadata() {
      return __async(this, null, function* () {
        try {
          return yield this.getMetadataAttempt();
        } catch (e) {
          if (e instanceof EtagMismatch) {
            this.cache.invalidate(this.source, e.message);
            return yield this.getMetadataAttempt();
          } else {
            throw e;
          }
        }
      });
    }
  };

  // src/index.js
  L.PMTilesLayer = L.VectorGrid.Protobuf.extend({
    initialize: function(url, options) {
      this.pmt = new PMTiles(url);
      L.VectorGrid.prototype.initialize.call(this, options);
    },
    _layerAdd: function(e) {
      this.pmt.getHeader().then((h) => {
        this.maxZoom = h.maxZoom;
        if (this.options.autoScale === "leaflet") {
          this.options.maxNativeZoom = h.maxZoom;
        }
        L.VectorGrid.prototype._layerAdd.call(this, e);
      });
    },
    createTile: function(coords, done) {
      let vectorTilePromise;
      const storeFeatures = this.options.getFeatureId;
      const tileSize = this.getTileSize();
      const renderer = this.options.rendererFactory(coords, tileSize, this.options);
      const tileBounds = this._tileCoordsToBounds(coords);
      const controller = new AbortController();
      const signal = controller.signal;
      if (storeFeatures) {
        this._vectorTiles[this._tileCoordsToKey(coords)] = renderer;
        renderer._features = {};
      }
      if (coords.z > this.maxZoom && this.options.autoScale !== false) {
        const pixelPoint = this._map.project(tileBounds.getCenter(), this.maxZoom).floor();
        const maxCoords = pixelPoint.unscaleBy(tileSize).floor();
        maxCoords.z = this.maxZoom;
        const newTileBounds = this._tileCoordsToBounds(maxCoords);
        vectorTilePromise = this._getVectorTilePromise(maxCoords, newTileBounds, signal).then(function renderTile(vectorTile) {
          if (vectorTile.layers && vectorTile.layers.length !== 0) {
            for (const layerName in vectorTile.layers) {
              const layer = vectorTile.layers[layerName];
              const deltaZoom = Math.abs(coords.z - maxCoords.z);
              const scale = 1 / Math.pow(2, deltaZoom);
              const scaledCoords = coords.scaleBy({ x: scale, y: scale });
              const x2 = scaledCoords.x % maxCoords.x * layer.extent;
              const y = scaledCoords.y % maxCoords.y * layer.extent;
              const segLen = layer.extent * scale;
              const n = y;
              const w = x2;
              const s = n + segLen;
              const e = w + segLen;
              const bounds = L.bounds(L.point(w, n), L.point(e, s));
              for (let i2 = 0; i2 < layer.features.length; i2++) {
                const geom = [];
                const feature = layer.features[i2];
                const featureGeometries = feature.loadGeometry();
                switch (feature.type) {
                  case 1: {
                    featureGeometries.forEach((featureGeom) => {
                      if (bounds.contains(featureGeom)) {
                        const point = featureGeom[0];
                        point.x = (point.x - bounds.min.x) / scale;
                        point.y = (point.y - bounds.min.y) / scale;
                        geom.push(point);
                      }
                    });
                    break;
                  }
                  default: {
                    featureGeometries.filter((featureGeom) => {
                      const points = featureGeom.map((p) => {
                        return L.point(p.x, p.y);
                      });
                      return bounds.overlaps(L.bounds(points));
                    });
                    featureGeometries.forEach((featureGeom) => {
                      featureGeom.map(function(point) {
                        point.x = (point.x - bounds.min.x) / scale;
                        point.y = (point.y - bounds.min.y) / scale;
                        return point;
                      });
                      geom.push(featureGeom);
                    });
                    break;
                  }
                }
                layer.features[i2].geometry = geom;
              }
              layer.features = layer.features.filter((feat) => {
                return feat.geometry.length > 0;
              });
            }
          }
          return new Promise(function(resolve) {
            return resolve(vectorTile);
          });
        });
      } else {
        vectorTilePromise = this._getVectorTilePromise(coords, tileBounds, signal);
      }
      vectorTilePromise.then(function renderTile(vectorTile) {
        if (vectorTile.layers && vectorTile.layers.length !== 0) {
          for (const layerName in vectorTile.layers) {
            this._dataLayerNames[layerName] = true;
            const layer = vectorTile.layers[layerName];
            const pxPerExtent = this.getTileSize().divideBy(layer.extent);
            const layerStyle = this.options.vectorTileLayerStyles[layerName] || this.options.style || L.Path.prototype.options;
            for (let i2 = 0; i2 < layer.features.length; i2++) {
              const feat = layer.features[i2];
              if (feat.geometry.length === 0) {
                continue;
              }
              if (this.options.filter instanceof Function && !this.options.filter(feat.properties, coords.z)) {
                continue;
              }
              let styleOptions = layerStyle;
              if (styleOptions instanceof Function) {
                styleOptions = styleOptions(feat.properties, coords.z);
              }
              if (!(styleOptions instanceof Array)) {
                styleOptions = [styleOptions];
              }
              if (!styleOptions.length) {
                continue;
              }
              const featureLayer = this._createLayer(feat, pxPerExtent);
              for (let j = 0; j < styleOptions.length; j++) {
                const style = L.extend({}, L.Path.prototype.options, styleOptions[j]);
                featureLayer.render(renderer, style);
                renderer._addPath(featureLayer);
              }
              if (this.options.interactive) {
                featureLayer.makeInteractive();
              }
            }
          }
        }
        if (this._map != null) {
          renderer.addTo(this._map);
        }
        L.Util.requestAnimFrame(done.bind(coords, null, null));
      }.bind(this));
      return renderer.getContainer();
    },
    _getVectorTilePromise: function(coords, tileBounds, signal) {
      return this.pmt.getZxy(coords.z, coords.x, coords.y, signal).then(function(arr) {
        if (arr) {
          return new Promise(function(resolve) {
            const pbf = new import_pbf.default(arr.data);
            return resolve(new import_vector_tile.VectorTile(pbf));
          });
        }
      }).then(function(vectorTile) {
        if (vectorTile) {
          for (const layerName in vectorTile.layers) {
            const feats = [];
            for (let i2 = 0; i2 < vectorTile.layers[layerName].length; i2++) {
              const feat = vectorTile.layers[layerName].feature(i2);
              feat.geometry = feat.loadGeometry();
              feats.push(feat);
            }
            vectorTile.layers[layerName].features = feats;
          }
          return vectorTile;
        } else {
          return {};
        }
      });
    }
  });
  var src_default = L.pmtilesLayer = function(url, options) {
    return new L.PMTilesLayer(url, options);
  };
})();
/*! ieee754. BSD-3-Clause License. Feross Aboukhadijeh <https://feross.org/opensource> */
