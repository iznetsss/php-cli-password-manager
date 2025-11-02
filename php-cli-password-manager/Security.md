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
Parameters:
OPSLIMIT_MODERATE
MEMLIMIT_MODERATE (~64–128 MiB)
Salt: exactly SODIUM_CRYPTO_PWHASH_SALTBYTES (16 bytes)

ENCRYPTION
-----------
Algorithm:
sodium_crypto_aead_xchacha20poly1305_ietf_*
Parameters:
nonce = 24 bytes from random_bytes()
AAD = "php-cli-password-manager:v1"

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
