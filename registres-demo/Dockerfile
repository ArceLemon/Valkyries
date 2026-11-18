# Faux registres de démonstration (état civil, nationalité, casier). Données fictives uniquement.
FROM php:8.4-cli-alpine
WORKDIR /app/registres-demo
COPY . .
EXPOSE 8081
CMD ["php", "-S", "0.0.0.0:8081", "router.php"]
