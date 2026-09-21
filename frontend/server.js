/**
 * Serveur custom pour un démarrage local / l’ancien déploiement cPanel manuel.
 * Le CI/CD (xsel-deploy-mutualise, stack nextjs-passenger) ignore ce fichier :
 * il déploie le server.js généré par `next build` avec output: "standalone".
 */
const { createServer } = require("http");
const next = require("next");

const port = parseInt(process.env.PORT || "3000", 10);
const dev = process.env.NODE_ENV !== "production";
const app = next({ dev });
const handle = app.getRequestHandler();

app.prepare().then(() => {
  createServer((req, res) => {
    handle(req, res);
  }).listen(port, () => {
    console.log(`> Ready on port ${port} (${dev ? "development" : "production"})`);
  });
});
