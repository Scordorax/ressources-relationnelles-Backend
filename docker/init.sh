#!/bin/bash
set -e

echo "⏳ Attente que la base de données soit prête..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
  sleep 2
done

echo "✅ Base de données disponible."

echo "🔄 Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "🔑 Génération des clés JWT si absentes..."
if [ ! -f config/jwt/private.pem ]; then
  php bin/console lexik:jwt:generate-keypair --skip-if-exists
fi

echo "🧹 Nettoyage du cache..."
php bin/console cache:clear

echo "🚀 Projet prêt !"
