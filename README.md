#  Pointeuse PWA (PHP/MySQL)

Une application web Progressive Web App (PWA) sécurisée pour la gestion du temps de travail, optimisée pour mobile et desktop.

##  Démo en ligne
Retrouvez le projet en production ici : [Lien vers ta pointeuse](https://pointeuse.bacadem.org) 

##  Fonctionnalités
- **Pointage Entrée/Sortie** en un clic.
- **Tableau de bord Admin** : Suivi des heures par employé.
- **Export CSV** : Génération de rapports pour la comptabilité.
- **Sécurité renforcée** : Protection CSRF, sessions sécurisées et PDO (Prepared Statements).
- **Mode PWA** : Installable sur smartphone comme une application native.

##  Installation & Configuration
1. Cloner le dépôt : `git clone https://github.com`
2. Importer le schéma de base de données : `install.sql`.
3. Configurer vos accès : copier `app/config.php.exemple` vers `app/config.php` et remplir les champs.

##  Stack Technique
- **Backend** : PHP 8.x
- **Base de données** : MySQL (InnoDB)
- **Frontend** : CSS3 (Mobile First), JavaScript Vanilla
- **Sécurité** : Hachage `password_hash()`, tokens CSRF.
