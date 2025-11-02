AUTHENTICATION
---------------
Master password hash:
password_hash(..., PASSWORD_BCRYPT)
Verification:
password_verify(...)

KDF (VAULT KEY DERIVATION)
--------------------------
Algorithm:
sodium_crypto_pwhash (Argon2id)

Profiles:
LIGHT    → libsodium INTERACTIVE
MEDIUM   → libsodium MODERATE  (default)
HEAVY    → libsodium SENSITIVE

Parameters stored in header:
name=argon2id, opslimit=MEDIUM, memlimit=MEDIUM, salt=16 bytes (base64)

ENCRYPTION
-----------
Algorithm:
sodium_crypto_aead_xchacha20poly1305_ietf_*
Parameters:
nonce = 24 bytes from random_bytes()
AAD = SHA-256 over the serialized VaultHeader (JSON, raw 32-byte output)
- Any change to the header breaks authentication and decryption.

ZEROIZATION
-----------
All secret buffers are cleared immediately:
sodium_memzero($secret)

RANDOMNESS
-----------
All randomness (salts, nonces, keys) from:
random_bytes()

-------------------------------------------
All cryptographic operations follow modern
best practices (Argon2id + XChaCha20-Poly1305).
No plaintext or reusable secrets are stored.
-------------------------------------------

DISPLAY POLICY
--------------
Passwords are never printed by default.
To display a password, the user must pass an explicit --show flag.


STRUCTURED DATA (DTO) BOUNDARIES
--------------------------------
I introduced strict DTOs at the storage boundary to harden the vault format:

- KdfParams validates Argon2id parameters and enforces a 16-byte salt (base64).
- VaultHeader fixes the schema (v1), checks bcrypt hash format, ISO 8601 timestamps and UUID v4.
- VaultBlob validates base64 and sizes (nonce = 24 bytes, cipher non-empty).

This prevents corrupted or tampered files from being accepted, keeps invariants in one place,
and reduces the chance of unsafe dynamic arrays leaking into critical crypto paths.
No plaintext is stored; secrets are still wiped with sodium_memzero().

INPUT VALIDATION / SANITIZATION (Milestone 3)
---------------------------------------------
A single validator enforces strict rules for user inputs:

- service: ^[A-Za-z0-9._:-]{1,255}$ (ASCII whitelist, trimmed)
- username: controls removed, trimmed, length 1..255 (UTF-8)
- password: length 1..4096 bytes, never trimmed or altered
- note: controls removed, trimmed, truncated to ≤250 chars (UTF-8)

Invalid inputs fail fast with short error codes (e.g., SVC_FORMAT, USR_LEN, PWD_LEN),
which are logged for auditing. Console echoes of user values are sanitized to strip
ANSI/control sequences to prevent terminal injection. This reduces attack surface,
stops garbage from reaching storage/crypto, and keeps secrets intact.

VAULT DATA MODEL 
------------------------------
Plaintext JSON: { "entries": [ ... ] }

Entry fields:
- id: UUID v4
- service: string (validated)
- username: string (validated)
- password: string (validated)
- note: string (sanitized)
- createdAt, updatedAt: ISO 8601 (UTC)

CRUD REPOSITORY
---------------
VaultRepository:
- state(data) -> normalized {entries}
- list(state, ?service)
- add(state, DTO) -> returns { state, created }
- getById(state, id)
- update(state, id, patch...) -> { state, updated }
- delete(state, id) -> { state, deleted }

Each CLI command asks master, decrypts, mutates in-memory, then save() via VaultAccess.
No long-lived keys between commands.
