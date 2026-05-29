# DEPLOY — Mister Co. Reports

Guía paso a paso para llevar la app a producción en un droplet de Digital Ocean.

Asume Ubuntu 22.04 LTS, dominio `reports.mister.com.py` apuntando al droplet, acceso SSH con un usuario `sudo`.

---

## 1. Provisionar droplet

- **Tamaño recomendado**: 2 vCPU / 4 GB RAM / 80 GB SSD (suficiente para 20 clientes).
- **Imagen**: Ubuntu 22.04 LTS.
- **DNS**: agregá registro A `reports → IP del droplet` en el proveedor de DNS de mister.com.py. Esperá propagación (≤ 30 min normalmente).

## 2. Hardening básico del servidor

```bash
ssh root@<IP>

# Usuario no-root con sudo
adduser misterco
usermod -aG sudo misterco

# Endurecer SSH (editar /etc/ssh/sshd_config):
#   PermitRootLogin no
#   PasswordAuthentication no   (después de copiar tu key con ssh-copy-id)
systemctl restart ssh

# Firewall
ufw allow OpenSSH
ufw allow 'Apache Full'
ufw enable

# Zona horaria
timedatectl set-timezone America/Asuncion
```

## 3. Stack: Apache + PHP 8.3 + MySQL 8

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php

sudo apt install -y \
    apache2 \
    mysql-server \
    php8.3 libapache2-mod-php8.3 \
    php8.3-{cli,mysql,curl,mbstring,xml,gd,zip,opcache} \
    composer git unzip certbot python3-certbot-apache

sudo a2enmod rewrite headers ssl http2
sudo systemctl enable --now apache2 mysql
```

### Asegurar MySQL

```bash
sudo mysql_secure_installation     # password root, eliminar usuarios anónimos, etc.

# Crear BD + usuario dedicado
sudo mysql <<'SQL'
CREATE DATABASE misterco_reports CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'misterco'@'localhost' IDENTIFIED BY 'CAMBIAR_ESTA_CLAVE_LARGA';
GRANT ALL PRIVILEGES ON misterco_reports.* TO 'misterco'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## 4. Desplegar el código

```bash
sudo mkdir -p /var/www
sudo chown -R $USER:www-data /var/www
cd /var/www
git clone https://github.com/keuch2/reports.git
cd reports

composer install --no-dev --optimize-autoloader

# Permisos: app legible por www-data, escritura sólo en storage/
sudo chown -R www-data:www-data /var/www/reports
sudo find /var/www/reports -type d -exec chmod 755 {} \;
sudo find /var/www/reports -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/reports/storage
sudo chmod +x /var/www/reports/bin/*.sh /var/www/reports/bin/*.php
```

## 5. Configurar `.env`

```bash
sudo cp deploy/.env.production.example .env
sudo chown www-data:www-data .env
sudo chmod 640 .env
sudo -u www-data php bin/generate-key.php   # genera APP_KEY
sudo nano .env                              # completar DB_PASSWORD, SMTP_*, APP_URL
```

## 6. Migrar BD + seed inicial

```bash
sudo -u www-data php bin/migrate.php
sudo -u www-data php bin/seed.php
```

> Esto crea las 19 tablas + el admin `admin@misterco.test` / `admin1234`.
> **Cambiá la contraseña del admin desde el flujo `/password/solicitar` antes de pasarlo a producción.**

## 7. Apache vhost + HTTPS

```bash
sudo cp deploy/apache-vhost.conf /etc/apache2/sites-available/reports.conf
sudo nano /etc/apache2/sites-available/reports.conf    # ajustar dominio si cambia
sudo a2ensite reports
sudo a2dissite 000-default
sudo apache2ctl configtest && sudo systemctl reload apache2

# Certificado SSL
sudo certbot --apache -d reports.mister.com.py
# Certbot adapta el vhost :443 y agrega las directivas SSL.
# Verificá renovación automática:
sudo systemctl status certbot.timer
```

## 8. Cron (backups + limpieza)

```bash
sudo crontab -u www-data /var/www/reports/deploy/cron-reports
sudo crontab -u www-data -l    # verificar que quedó instalado
```

Cron incluye:
- **03:00** — limpia PDFs > 180 días, previews > 30 días, logs > 60 días.
- **04:00** — backup BD + tar de PDFs en `storage/backups/`, retención 30 días.

> No hay cron de sincronización Meta: la importación es manual on-demand.

## 9. Backup remoto (opcional pero recomendado)

```bash
sudo apt install -y s3cmd
sudo -u www-data s3cmd --configure   # ingresar credenciales DO Spaces
# Editar bin/backup.sh y descomentar el bloque `s3cmd sync ...` al final.
```

## 10. Smoke test post-deploy

```bash
curl -I https://reports.mister.com.py/login
# Debe responder 200 con cabeceras: Content-Security-Policy, Strict-Transport-Security,
# X-Frame-Options DENY, etc.

# Login en el browser → conectar token Meta → importar primera cuenta → exportar PDF.
```

---

## Rotación de claves

- **`APP_KEY`**: rotarla invalida todos los secretos cifrados en BD (token Meta + 2FA). Después de rotar, hay que reconectar Meta desde `/admin/meta` y re-enrollar 2FA. Backup BD antes.
- **Token Meta**: rotar desde el Business Manager → pegarlo en `/admin/meta`. La app sobrescribe el cifrado.
- **Contraseña DB**: cambiar en MySQL + actualizar `.env` + `systemctl reload apache2`.

## Actualizar la app

```bash
cd /var/www/reports
sudo -u www-data git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php bin/migrate.php
sudo systemctl reload apache2
```

## Restaurar un backup

```bash
gunzip < /var/www/reports/storage/backups/db-misterco_reports-YYYYMMDD-HHMMSS.sql.gz \
    | mysql -u misterco -p misterco_reports
tar -xzf /var/www/reports/storage/backups/reportes-YYYYMMDD-HHMMSS.tar.gz \
    -C /var/www/reports/storage/
```

## Monitoreo mínimo

- `tail -f /var/log/apache2/reports-error.log` para errores web.
- `tail -f /var/www/reports/storage/logs/php-$(date +%Y-%m-%d).log` para errores PHP.
- Vista `/admin/auditoria` para acciones de usuarios.
- Vista `/admin/importaciones` para historial de imports con errores Meta.
