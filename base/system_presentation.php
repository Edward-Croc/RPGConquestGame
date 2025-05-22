<?php

require_once '../base/base_php.php';
$pageName = 'system_presentation';

require_once '../base/baseHtml.php';

?> 
<div class="factions">
<h2>Présentation du système : </h2>
<p>Bienvenue sur la page de présentation du système de jeu. Ce système repose sur une simulation stratégique en tour par tour mettant en scène des <strong>contrôleurs</strong>, leurs <strong>agents</strong>, des <strong>zones</strong> et des <strong>lieux spécifiques</strong> à découvrir, défendre ou conquérir.</p>

<h3>1. Contrôleurs et Agents</h3>
<p>Chaque contrôleur gère plusieurs agents qu’il peut envoyer sur le terrain pour accomplir différentes actions : enquête, attaque, conquête, etc. Ces agents possèdent des <strong>valeurs dynamiques</strong> (enquête, attaque, défense) qui sont calculées à chaque tour en fonction de leurs pouvoirs, des bonus passifs/actifs, ainsi que du contrôle de la zone dans laquelle ils se trouvent.</p>

<h3>2. Actions possibles</h3>
<ul>
  <li><strong>Investiguer :</strong> L’agent tente de découvrir des lieux secrets dans sa zone.</li>
  <li><strong>Attaquer :</strong> Vise à affaiblir l’influence ennemie.</li>
  <li><strong>Défendre :</strong> Renforce la défense d’une zone contrôlée.</li>
</ul>
<p>Chaque action peut être passive (valeurs fixes) ou active (valeurs déterminées par lancer de dés).</p>

<h3>3. Zones et Lieux</h3>
<p>Le monde est divisé en zones, certaines contrôlées par un joueur. Ces zones peuvent contenir des lieux secrets, qui ont une difficulté de découverte. Lorsqu’un agent enquête dans une zone ennemie, il peut découvrir :</p>
<ul>
  <li><strong>Le nom du lieu</strong> si la différence de valeur est suffisante</li>
  <li><strong>Sa description</strong> si la valeur est encore plus élevée</li>
  <li><strong>Et s’il est destructible</strong>, une ligne spéciale est ajoutée au rapport</li>
</ul>

<h3>4. Mécanique de découverte</h3>
<p>Un système avancé génère des rapports personnalisés selon les écarts d’enquête. Ces textes sont dynamiquement sélectionnés depuis la table <code>config</code> et insérés dans le rapport pour une narration plus immersive.</p>

<h3>5. Tour par tour et calculs automatiques</h3>
<p>À chaque tour, des fonctions PHP automatisées :</p>
<ul>
  <li>Calculent les valeurs des agents</li>
  <li>Met à jour la défense des zones contrôlées</li>
  <li>Gèrent l’enregistrement et la découverte de lieux par les contrôleurs</li>
</ul>

<h3>6. Configuration dynamique</h3>
<p>Presque tous les paramètres du système sont <strong>configurables dynamiquement</strong> dans la base de données (<code>config</code>) : valeurs de bonus, textes, modes de calcul, seuils de découverte, etc.</p>

<h3>7. Debug et suivi</h3>
<p>Un mode <code>DEBUG_REPORT</code> permet d’afficher l’intégralité des calculs et des comparaisons réalisées à chaque tour pour le suivi du développement ou le test des équilibres.</p>

<p>Ce système modulaire et extensible permet une simulation riche, à la fois stratégique et narrative, avec une part de mystère liée à la découverte de lieux secrets dans un monde partagé entre factions rivales.</p>

</div>
