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
Salt:
vaultSalt = 16–32 bytes from random_bytes()

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