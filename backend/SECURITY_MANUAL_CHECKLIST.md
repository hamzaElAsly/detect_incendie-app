# Checklist manuelle de sécurité et infrastructure

Date de préparation : 29 juillet 2026. Ces contrôles ne sont pas déclarés validés par les tests locaux.

## HTTPS, proxy et cookies

- [ ] TLS 1.2+ actif avec certificat valide et renouvellement supervisé
- [ ] Redirection HTTP vers HTTPS testée au reverse proxy
- [ ] `APP_ENV=production` et `APP_DEBUG=false`
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`
- [ ] HSTS observé uniquement en HTTPS et compatible avec tous les sous-domaines
- [ ] En-têtes transmis sans duplication ni suppression par le reverse proxy

## CORS et CSP

- [ ] `CORS_ALLOWED_ORIGINS` contient uniquement les origines frontend de production
- [ ] Une origine non autorisée est refusée sur l'infrastructure réelle
- [ ] Aucun credential CORS n'est activé
- [ ] CSP Report-Only observée dans Chrome, Firefox, Edge et Safari
- [ ] Violations CSP collectées et sources réellement nécessaires inventoriées
- [ ] Migration CSP vers une politique enforce avec nonce/hash planifiée

## Fichiers et serveur

- [ ] Répertoires `storage` non exécutables par le serveur web
- [ ] Disque incident privé inaccessible par URL directe
- [ ] Permissions minimales du compte PHP vérifiées
- [ ] Antivirus/scan de contenu évalué pour les vidéos industrielles
- [ ] Taille maximale PHP/proxy cohérente avec les validations Laravel
- [ ] Nettoyage des fichiers orphelins et politique de rétention supervisés

## Secrets et accès

- [ ] Aucun `.env`, log, dump, certificat ou secret dans les artefacts de déploiement publics
- [ ] Identifiants de démonstration désactivés ou remplacés
- [ ] Secrets de production stockés dans un gestionnaire dédié
- [ ] Rotation API Client testée avec un véritable Edge Agent
- [ ] Accès serveur, base, stockage et sauvegardes soumis au moindre privilège
- [ ] Procédure de révocation d'urgence documentée et testée

## Dépendances et CI

- [ ] `composer audit --locked` réussi dans une CI connectée
- [ ] `npm audit --audit-level=moderate` réussi dans une CI connectée
- [ ] Alertes Dependabot/Renovate ou équivalent activées
- [ ] Tests, Pint et build bloquent une livraison en cas d'échec

## Exploitation

- [ ] Sauvegarde chiffrée et restauration réellement testée
- [ ] Logs centralisés avec accès restreint et rétention définie
- [ ] Alertes sur refus Edge répétés sans journalisation des bearer tokens
- [ ] Horloges serveurs/agents synchronisées par NTP
- [ ] Recette multi-navigateurs de `RESPONSIVE_MANUAL_CHECKLIST.md` effectuée
