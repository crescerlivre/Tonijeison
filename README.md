### Para iniciar corretamente 

#### Instale o projeto e crie as crons

```bash
composer insall
```

```bash
crontab -e
```

```bash
cd /opt/apibrasil-whatsapp && php7.4 artisan schedule:run >> /dev/null 2>&1
```

```bash
cd /opt/divulgawhatsapp && php7.4 artisan schedule:run >> /dev/null 2>&1
```
