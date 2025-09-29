<?php
$noConnection = true;
require_once '../base/basePHP.php';
$pageName = 'systemPresentation';

require_once '../base/baseHTML.php';

?> 
<div class="factions">
<h2>Présentation du système : </h2>
<p>Bienvenue sur la page de présentation du système de jeu RPG Game Conquest.<br>
Ce système repose sur une simulation stratégique en tour par tour mettant en scène des <strong>contrôleurs/factions</strong>,
 leurs <strong>agents</strong>, des <strong>zones</strong> et des <strong>lieux spécifiques</strong> à découvrir, défendre ou conquérir.<br>
Ce système est créé pour assiter le fonctionnement des mécaniques de jeu d'une <strong>soirée enquête</strong>.
Il n'est pas prévu pour fonctionner sans une phase de négociations entre joueurs à chaque tour.
</p>

<h3>1. Contrôleurs/Factions et Agents</h3>
<p>Chaque contrôleur/faction gère son réseau d'agents, qu’il peut envoyer sur le terrain pour accomplir différentes actions : enquête, attaque, conquête, etc.<br>
Chaque contrôleur a un numéro de réseau unique, qu'il sera possible aux autres joueurs de découvrir lors des enquêtes.
  <ul>
  <li>Un contrôleur peut chaque tour <strong>recruter</strong> un ou plusieurs agents, selon les circonstances.<ul>
    <li>Il existe <strong>deux types</strong> de recrutement : celui qui donne un choix entre plusieurs agents et qui nécessite d'avoir une place forte active, et <strong>premier venu</strong> qui donne un agent unique à prendre ou à laisser.</li>
    <li>Lors du <strong>recrutement</strong> le nom, prénom et deux <strong>capacités sont tirées au sort</strong>. Le contrôleur peut alors l'affecter à une zone d'action.</li>
    <li> (<strong>Attention:</strong> une fois un recrutement commencé il est décompté pour le tour, ne quittez pas la page !)</li>
  </ul></li>
  <li>Un contrôleur peut donner des <strong>capacités</strong> supplémentaires à ses agents au fil du temps. Plus un agent est ancien, plus il sera puissant.</li>
  <li>Un contrôleur pourra parfois, en fonction de la configuration (contrôle d'une zone, faction, etc.), donner d'autres avantages à son serviteur.</li>
  <li>Ces agents possèdent donc des <strong>valeurs dynamiques</strong> (enquête, attaque, défense) qui sont <strong>calculées à chaque tour</strong> en fonction de leurs pouvoirs, avantages et de bonus passifs ou actifs, ainsi que du contrôle de la zone dans laquelle ils se trouvent.</li>
  </ul>
</p>
<h3>2. Actions possibles d'Agents</h3>
<p> Chaque tour un agent peut faire <strong>une action parmi</strong> les suivantes :
<ul>
  <li><strong>Surveiller :</strong> L’agent ne prend pas de risques, mais reste à l'affut des autres agents dans la zone.</li>
  <li><strong>Investiguer :</strong> L’agent tente de découvrir les autres agents et les lieux secrets dans sa zone, au risque d'échouer.</li>
  <li><strong>Attaquer :</strong> Attaque les agents choisis par le contrôleur parmi la liste fournie. Il y a 4 résultats possibles à une attaque : 
    <ul>
      <li> <i>Défaite totale :</i> l'attaquant disparait, éliminé par une contre et la cible apprend son nom.</li>
      <li> <i>Echec :</i> l'attaquant échoue et la cible apprend son nom.</li>
      <li> <i>Réussite :</i> l'attaquant élimine sa cible.</li>
      <li> <i>Capture :</i> l'attaquant capture sa cible vivante le contrôleur obtient l'accès aux rapports de la cible.</li>
    </ul>
  </li>
  <li><strong>Se cacher :</strong> L’agent tente de se dissimuler des autres agents dans sa zone.</li>
  <li><strong>Revendiquer :</strong> Cherche à prendre le contrôle de la zone, et la consacre au contrôleur choisi.
  Renforce la défense d’une zone déjà contrôlée. Cette action <strong>révélera</strong> l'agent qui l'effectue.</li>
</ul>
</p>
<p> Chaque tour un agent peut <strong>librement</strong> et sans malus : <ul>
    <li><strong>Déménager :</strong> changer de zone</li>
    <li><strong>Etre offert à un autre :</strong> changer de contrôleur</li>
</ul></p>
<p>
  Les <strong>résultats d'une enquête</strong> (surveillance ou investigation) sur un agent contiendront par ordre de difficulté d'obtention : <ul>
    <li> Le nom, les capacités aléatoires et l'action en cours d'un agent. </li>
    <li> Les capacités et modifications données par son contrôleur. </li>
    <li> Le numéro du réseau dont fait partie l'agent. </li>
    <li> Le nom du contrôleur qui domine le réseau.</li>
  </ul>
</p>
<h3>3. Zones, Lieux et Artefacts</h3>
<p>La carte du monde est divisée en <strong>zones</strong>, qui peuvent être contrôlées par un <strong>contrôleur/faction</strong>.
  Ces zones peuvent contenir un ou plusieurs lieux secrets et les places fortes des contrôleurs, qui ont chacun une difficulté de découverte différente.
  Lorsqu’un agent enquête dans une zone ennemie, il peut découvrir :</p>
<ul>
  <li><strong>Le nom du lieu</strong> si la différence de valeur est suffisante</li>
  <li><strong>Sa description</strong> incluant des informations secrètes sur la zone ou les personnages liés si la valeur est encore plus élevée</li>
  <li><strong>Et s’il est destructible</strong>, le rapport le mentionne et l'action de destruction ou de conquête apparaîtra dans la page du contrôleur et la page des zones</li>
</ul>
<p> L'<strong>attaque d'un lieu</strong>  est résolue immédiatement par le système, il prend en compte pour l'attaquant : <ul>
    <li>une valeur fixe secrète </li>
    <li>le nombre de serviteurs appartenant à l'attaquant dans la zone </li>
    <li>les capacités du contrôleur attaquant </li>
    <li>si la zone est conquise par le contrôleur </li>
  </ul>
  qui est comparée à la <strong>défense du lieu</strong> , une valeur fixe si le lieu n'est lié à aucun contrôleur, ou si le lieu est lié à un contrôleur (place forte, etc) composée de : 
  <ul>
    <li>la défense fixe du lieu </li>
    <li>les capacités du contrôleur défenseur  </li>
    <li>le nombre de serviteurs du contrôleur défenseur dans la zone </li>
    <li>si la zone est conquise par le contrôleur </li>
    <li>parfois la durée d'installation du lieu </li>
</ul>
</p>
<p>
    Lors d'une attaque réussie sur un lieu, si ce lieu contient des artéfacts récupérables, alors ils seront transférés dans la place forte du contrôleur.
</p>

<h3>4. Mécaniques de jeu (pour les nerds et power gamers)</h3>
<p>Le système crée des rapports personnalisés selon les valeurs d’enquête, d'attaque et de défense et les actions menées.
  Ces textes sont conservés d'un tour à l'autre pour former l'historique de chaque agent.
</p>

<p>À chaque tour de jeu, des calculs automatisés sont effectués dans l'ordre suivant :</p>
<ul>
  <li>Calculer les valeurs des agents (enquête, attaque, défense) et les mentionne sur leur rapport du tour</li>
  <li>Mettre à jour la difficulté de découverte des lieux contrôlés</li>
  <li>Résoudre les attaques entre agents</li>
  <li>Déterminer les résultats des investigations et surveillances</li>
  <li>Gérer la découverte de lieux par les agents et leur contrôleur</li>
  <li>Trancher le résultat des revendications de zones</li>
  <li>Remettre à 0 les compteurs de recrutement</li>
  <li>Avancer le compteur de tours</li>
</ul>

<h2>Pour les futurs organisateurs : </h2>
<h3>1. Configuration dynamique</h3>
<p> Presque tous les paramètres du système sont <strong>configurables dynamiquement</strong> dans la base de données (<code>config</code>) : valeurs de bonus, textes, modes de calcul, seuils de découverte, résultats des attaques etc.
  Deux soirées enquêtes complètes sont prévues pour être publiées en utilisant ce système, il vous sera possible de vous baser sur leur paramétrage.</p>
<h3>2. Debug et suivi</h3>
<p>Un mode <code>DEBUG_REPORT</code> permet d’afficher l’intégralité des calculs et des comparaisons réalisés à chaque tour pour le suivi du développement ou le test des équilibrages.</p>
<h3>3. Contact</h3>
<p> Il est possible de récupérer les sources libres d'accès de ce système sur le GitHub suivant <a href="https://github.com/Edward-Croc/RPGConquestGame" target="_blank">https://github.com/Edward-Croc/RPGConquestGame</a>.
   Vous pouvez vous rendre sur la page <a href="https://github.com/Edward-Croc/RPGConquestGame/issues" target="_blank">issues</a> pour y déclarer les bugs.
   Et en dernier recours voici <details> <summary>mon email : </summary> <p>lenainblanc[at]wanadoo.fr</p> </details>
</p>
</div>
