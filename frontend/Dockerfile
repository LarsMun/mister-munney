# Gebaseerd op Node 20
FROM node:20

LABEL authors="lars"

# Werkdirectory in container
WORKDIR /app

# Zet dependency-bestanden erin
COPY package.json ./
COPY package-lock.json ./

# Installeer dependencies
RUN npm install

# Fix permissions voor executables in node_modules
RUN chmod +x node_modules/.bin/*

# Voeg de rest van de app toe
COPY . .

# Copy entrypoint script voor runtime permission fix
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Use entrypoint to fix permissions at runtime
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Dev server starten (Vite)
CMD ["npm", "run", "dev", "--", "--host"]
