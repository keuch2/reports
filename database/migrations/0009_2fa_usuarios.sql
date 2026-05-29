ALTER TABLE usuarios
    ADD COLUMN twofa_secret_cifrado TEXT NULL AFTER password_hash,
    ADD COLUMN twofa_habilitado TINYINT(1) NOT NULL DEFAULT 0 AFTER twofa_secret_cifrado,
    ADD COLUMN twofa_backup_codes_cifrado TEXT NULL AFTER twofa_habilitado;
