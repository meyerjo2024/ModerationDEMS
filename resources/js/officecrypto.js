// Opens a password-protected .docx entirely in the browser (MS-OFFCRYPTO "agile" encryption, Word 2010+).
// The password never leaves the page.
import * as CFB from 'cfb';

const HASH = { SHA1: 'SHA-1', SHA256: 'SHA-256', SHA384: 'SHA-384', SHA512: 'SHA-512' };
const BLOCK = {
    verifierInput: [0xfe, 0xa7, 0xd2, 0x76, 0x3b, 0x4b, 0x9e, 0x79],
    verifierValue: [0xd7, 0xaa, 0x0f, 0x6d, 0x30, 0x61, 0x34, 0x4e],
    keyValue: [0x14, 0x6e, 0x0b, 0xe7, 0xab, 0xac, 0xd0, 0xd6],
};

export class WrongPassword extends Error {}
export class UnsupportedEncryption extends Error {}

export const isOle = (u8) => u8.length > 8 && u8[0] === 0xd0 && u8[1] === 0xcf && u8[2] === 0x11 && u8[3] === 0xe0;

const cat = (...p) => {
    const out = new Uint8Array(p.reduce((n, a) => n + a.length, 0));
    let o = 0;
    p.forEach((a) => (out.set(a, o), (o += a.length)));
    return out;
};
const le32 = (n) => new Uint8Array([n & 255, (n >>> 8) & 255, (n >>> 16) & 255, (n >>> 24) & 255]);
const b64 = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
const utf16 = (s) => {
    const u = new Uint8Array(s.length * 2);
    for (let i = 0; i < s.length; i++) (u[2 * i] = s.charCodeAt(i) & 255), (u[2 * i + 1] = s.charCodeAt(i) >> 8);
    return u;
};
const digest = async (alg, data) => new Uint8Array(await crypto.subtle.digest(alg, data));
const fit = (a, n, pad = 0x36) => (a.length >= n ? a.slice(0, n) : cat(a, new Uint8Array(n - a.length).fill(pad)));

/** AES-CBC decrypt without padding (WebCrypto insists on PKCS#7, so a padding block is appended that satisfies it). */
async function aesNoPad(keyBytes, iv, data) {
    const key = await crypto.subtle.importKey('raw', keyBytes, 'AES-CBC', false, ['encrypt', 'decrypt']);
    const last = data.slice(data.length - 16);
    const padBlock = new Uint8Array(await crypto.subtle.encrypt({ name: 'AES-CBC', iv: last }, key, new Uint8Array(16).fill(16))).slice(0, 16);
    return new Uint8Array(await crypto.subtle.decrypt({ name: 'AES-CBC', iv }, key, cat(data, padBlock)));
}

export async function decryptDocx(buffer, password) {
    const cfb = CFB.read(new Uint8Array(buffer), { type: 'array' });
    const info = CFB.find(cfb, '/EncryptionInfo')?.content;
    const pkg = CFB.find(cfb, '/EncryptedPackage')?.content;
    if (!info || !pkg) throw new UnsupportedEncryption('This file is not an encrypted Word document.');
    const major = info[0] | (info[1] << 8), minor = info[2] | (info[3] << 8);
    if (!(major === 4 && minor === 4)) throw new UnsupportedEncryption('This protection type is not supported. Ask the examiner to save the paper as a PDF or without a password.');

    const xml = new DOMParser().parseFromString(new TextDecoder().decode(Uint8Array.from(info).slice(8)), 'text/xml');
    const kd = xml.getElementsByTagNameNS('*', 'keyData')[0];
    const ek = xml.getElementsByTagNameNS('*', 'encryptedKey')[0];
    if (!kd || !ek) throw new UnsupportedEncryption('Unreadable encryption header.');
    const alg = HASH[ek.getAttribute('hashAlgorithm')];
    const dataAlg = HASH[kd.getAttribute('hashAlgorithm')];
    if (!alg || !dataAlg || kd.getAttribute('cipherAlgorithm') !== 'AES' || ek.getAttribute('cipherAlgorithm') !== 'AES') throw new UnsupportedEncryption('Unsupported encryption algorithm.');
    const spin = parseInt(ek.getAttribute('spinCount'), 10);
    const keyBytes = parseInt(ek.getAttribute('keyBits'), 10) / 8;
    const salt = b64(ek.getAttribute('saltValue'));

    let h = await digest(alg, cat(salt, utf16(password)));
    for (let i = 0; i < spin; i++) h = await digest(alg, cat(le32(i), h));
    const derive = async (blockKey) => fit(await digest(alg, cat(h, new Uint8Array(blockKey))), keyBytes);
    const iv = fit(salt, 16, 0);

    const verifier = await aesNoPad(await derive(BLOCK.verifierInput), iv, b64(ek.getAttribute('encryptedVerifierHashInput')));
    const expected = await aesNoPad(await derive(BLOCK.verifierValue), iv, b64(ek.getAttribute('encryptedVerifierHashValue')));
    const actual = await digest(alg, verifier.slice(0, parseInt(ek.getAttribute('saltSize'), 10)));
    if (!actual.every((b, i) => b === expected[i])) throw new WrongPassword('That password is not correct.');

    const secret = (await aesNoPad(await derive(BLOCK.keyValue), iv, b64(ek.getAttribute('encryptedKeyValue')))).slice(0, keyBytes);
    const dataSalt = b64(kd.getAttribute('saltValue'));
    const size = new DataView(Uint8Array.from(pkg.slice(0, 8)).buffer).getUint32(0, true);
    const enc = Uint8Array.from(pkg.slice(8));
    const parts = [];
    for (let i = 0, off = 0; off < enc.length; i++, off += 4096) {
        const seg = enc.slice(off, Math.min(off + 4096, enc.length));
        const segIv = fit(await digest(dataAlg, cat(dataSalt, le32(i))), 16, 0);
        parts.push(await aesNoPad(secret, segIv, seg));
    }
    return cat(...parts).slice(0, size).buffer;
}
