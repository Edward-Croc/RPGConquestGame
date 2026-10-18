<?php
$noConnection = true;
require_once '../base/basePHP.php';
$pageName = 'systemPresentation';

require_once '../base/baseHTML.php';

$timeValue = (string) getConfig($gameReady, 'timeValue');
$zoneType = (string) getConfig($gameReady, 'textForZoneType');
$labelInvestigate = ucfirst((string) getConfig($gameReady, 'txt_inf_investigate'));
$labelPassive = ucfirst((string) getConfig($gameReady, 'txt_inf_passive'));
$labelHide = ucfirst((string) getConfig($gameReady, 'txt_inf_hide'));
$minRoll = (int) getConfig($gameReady, 'MINROLL');
$maxRoll = (int) getConfig($gameReady, 'MAXROLL');
$passiveVal = (int) getConfig($gameReady, 'PASSIVEVAL');
$claimEnabled = in_array(getConfig($gameReady, 'claimMode'), ['worker', 'worker_leader'], true);
$agentLocationAttack = getConfig($gameReady, 'locationAttackMode') === 'agent_attack_defence';
$ressourcesEnabled = getConfig($gameReady, 'ressource_management') == 'TRUE';
$captureSet = getConfig($gameReady, 'guideCaptures');
$hideDiscretion = (int) getConfig($gameReady, 'HIDE_ENQUETE_FLAT_BONUS');
$hideDefence = (int) getConfig($gameReady, 'HIDE_DEFENCE_FLAT_BONUS');
$defendLocationBonus = (int) getConfig($gameReady, 'DEFEND_LOCATION_DEFENCE_FLAT_BONUS');
$ownedArtefactsTitle = rtrim((string) getConfig($gameReady, 'textOwnedArtefacts'), ' :');
$claimMode = getConfig($gameReady, 'claimMode');
$locationAttackMode = getConfig($gameReady, 'locationAttackMode');
$configInt = fn (string $key): int => (int) getConfig($gameReady, $key);
$riposteActive = $configInt('RIPOSTACTIVE') !== 0;
$overwhelmMorethan = getConfig($gameReady, 'locationOverwhelmMode') === 'morethan';
// The actions that observe the other agents of the zone, read as the investigation reads them.
$investigatingVerbs = array_map(
    fn (string $action): string => (string) getConfig($gameReady, 'txt_ps_' . $action),
    explode(',', str_replace("'", '', getValidatedInvestigateActionsForSql($gameReady)))
);
$lastInvestigatingVerb = array_pop($investigatingVerbs);
$investigatingText = empty($investigatingVerbs)
    ? $lastInvestigatingVerb
    : implode(', ', $investigatingVerbs) . ' ou ' . $lastInvestigatingVerb;

// What an action adds on one axis : a die when it is active on that axis, the fixed value otherwise.
$actionRoll = function (string $axis, string $action) use ($gameReady, $minRoll, $maxRoll, $passiveVal): string {
    $activeActions = explode(',', str_replace("'", '', (string) getConfig($gameReady, 'active' . $axis . 'Actions')));
    if (!in_array($action, $activeActions, true)) {
        return (string) $passiveVal;
    }
    return $minRoll === 1 ? '1d' . $maxRoll : sprintf('un dé de %d à %d', $minRoll, $maxRoll);
};

// Actions an agent keeps from one turn to the next, the others fall back to watching.
$continuingActions = [];
foreach (['investigate', 'hide', 'claim', 'attack', 'attack_location', 'defend_location'] as $action) {
    if ((int) getConfig($gameReady, 'continuing_' . $action . '_action') === 1) {
        $continuingActions[] = (string) getConfig($gameReady, 'txt_inf_' . $action);
    }
}

?>
<div class="factions guide">
<h2 class="title is-3">Guide du joueur</h2>
<div class="box guide-section">
<h3 class="title is-4">1. Se connecter et naviguer</h3>
<?= guideCaptureFigure($captureSet, 'menu', 'Le menu, ouvert par le bouton ☰') ?>
<?= guideCaptureFigure($captureSet, 'login', 'La page de connexion') ?>
<p>Ce système accompagne une <strong>soirée enquête</strong> : il tient la mécanique de la partie, en tour par tour,
pendant que les joueurs négocient entre eux. <strong>Le jeu diplomatique reste indispensable</strong> : le système
ne remplace ni les alliances, ni les trahisons, ni l'orga.</p>
<p>Chaque joueur dirige une <strong>faction</strong> qui envoie ses <strong>agents</strong> sur les
<strong><?= htmlspecialchars($zoneType) ?>s</strong> de la carte pour enquêter, attaquer, revendiquer et découvrir des lieux secrets.
Un tour de jeu s'appelle ici un <strong><?= htmlspecialchars($timeValue) ?></strong>.</p>
<p><strong>Vos identifiants sont donnés par l'orga. Pour toute question, allez voir l'orga !</strong></p>
<p>Le bouton <strong>☰</strong>, en haut à gauche de chaque page, ouvre le menu :</p>
<ul>
  <li><strong>Accueil</strong> : l'introduction de la partie, puis votre faction, les <?= htmlspecialchars($zoneType) ?>s et vos agents sur une seule page.</li>
  <li><strong>Ma Faction</strong> : votre base, vos lieux, les informations à donner ou reçues.</li>
  <li><strong>Agents de la faction</strong> : recruter et diriger vos agents.</li>
  <?php if ($ressourcesEnabled) { ?>
  <li><strong>Ressources</strong> : ce que possède votre faction, ce qu'elle gagnera, et les dons.</li>
  <?php } ?>
  <li><strong>Les Zones</strong> : la carte et ce que vous savez de chaque <?= htmlspecialchars($zoneType) ?>.</li>
  <li><strong>Le Système</strong> : ce guide.</li>
  <li><strong>Mon compte</strong> : vos factions et votre mot de passe.</li>
</ul>
</div>

<div class="box guide-section">
<h3 class="title is-4">2. Un <?= htmlspecialchars($timeValue) ?> de jeu</h3>
<p>Pendant le <?= htmlspecialchars($timeValue) ?>, vous <strong>choisissez</strong> l'action de chaque agent. Rien n'est
résolu tout de suite : l'orga lance la <strong>fin de <?= htmlspecialchars($timeValue) ?></strong>, et le système résout
alors toutes les actions, dans cet ordre :</p>
<ol>
  <?php if ($ressourcesEnabled) { ?><li>Les ressources du <?= htmlspecialchars($timeValue) ?> sont versées.</li><?php } ?>
  <li>Les valeurs de chaque agent sont calculées et notées dans son rapport.</li>
  <li>Les attaques entre agents.</li>
  <li>Les attaques de lieux.</li>
  <li>Les revendications de <?= htmlspecialchars($zoneType) ?>s.</li>
  <?php if ($ressourcesEnabled) { ?><li>Les ressources qui dépendent des <?= htmlspecialchars($zoneType) ?>s tenu.es.</li><?php } ?>
  <li>Les enquêtes sur les agents.</li>
  <li>La recherche des lieux.</li>
  <li>Le compteur de recrutement repart à zéro et un nouveau <?= htmlspecialchars($timeValue) ?> commence.</li>
</ol>
<p>Les rapports de vos agents racontent ensuite ce qu'ils ont vécu. <strong>Conseil : prenez des notes sur papier.</strong></p>
</div>

<div class="workers guide-band"><div class="box guide-section">
<h3 class="title is-4">3. Vos agents</h3>
<?= guideCaptureFigure($captureSet, 'agents', 'La liste des agents') ?>
<h4 class="title is-5">Recruter</h4>
<?= guideCaptureFigure($captureSet, 'recrutement', 'Le choix entre plusieurs candidats') ?>
<ul>
  <li><strong>Recruter un serviteur</strong> propose plusieurs candidats et demande d'avoir une base. Vous en choisissez un seul.</li>
  <li><strong>Prendre le premier venu</strong> propose un candidat unique, à prendre ou à laisser.</li>
  <li>Le nom et les capacités de chaque candidat sont tirés au sort. Vous choisissez où l'envoyer, et une première maîtrise à lui enseigner.</li>
  <li><strong>ATTENTION : UN RECRUTEMENT OUVERT COMPTE POUR LE <?= htmlspecialchars(mb_strtoupper($timeValue)) ?>. NE QUITTEZ PAS LA PAGE SANS RECRUTER, SINON L'AGENT EST PERDU !</strong></li>
</ul>

<h4 class="title is-5">L'action prévue en fin de <?= htmlspecialchars($timeValue) ?> (et dans quel <?= htmlspecialchars($zoneType) ?>)</h4>
<?= guideCaptureFigure($captureSet, 'agent', "Les actions d'un agent") ?>
<p>Chaque agent fait <strong>une seule action</strong> par <?= htmlspecialchars($timeValue) ?>, dans son <?= htmlspecialchars($zoneType) ?>.
Les formules donnent la valeur de l'agent pour cette action ou « enquête », « attaque » et « défense » sont les caractéristiques de l'agent.</p>
<p><strong>Actions de surveillance :</strong></p>
<ul>
  <li><strong><?= htmlspecialchars($labelInvestigate) ?> : <?= $actionRoll('Investigate', 'investigate') ?> + enquête.</strong> L'agent cherche activement les agents et les lieux secrets.</li>
  <li><strong><?= htmlspecialchars($labelPassive) ?> : <?= $actionRoll('Investigate', 'passive') ?> + enquête.</strong> L'agent reste à l'affût, sans prendre de risque, mais repère tout de même les agents et les lieux.</li>
  <?php if ($agentLocationAttack) { ?>
  <li><strong>Défendre le lieu : défense +<?= $defendLocationBonus ?>.</strong> L'agent défend l'un de vos lieux, et surveille en même temps. Les duels entre attaquants et défenseurs sont résolus en fin de <?= htmlspecialchars($timeValue) ?>.</li>
  <?php } ?>
</ul>
<p><strong>Actions passives :</strong></p>
<ul>
  <li><strong><?= htmlspecialchars($labelHide) ?> : pas d'enquête, discrétion +<?= $hideDiscretion ?>, défense +<?= $hideDefence ?>.</strong> L'agent se fait oublier et ne cherche rien.</li>
  <?php if ($claimEnabled) { ?>
  <li><strong>Revendiquer : </strong>
    L'agent tente de placer le <?= htmlspecialchars($zoneType) ?> sous vôtre contrôle la bannière de la faction de votre choix, ou <strong>protège</strong> un <?= htmlspecialchars($zoneType) ?> déjà tenu. <strong>Revendiquer révèle l'agent.</strong></li>
  <?php } ?>
</ul>
<p><strong>Actions d'attaques :</strong></p>
<ul>
  <li><strong>Attaquer : <?= $actionRoll('Attack', 'attack') ?> + attaque contre <?= $actionRoll('Defence', 'passive') ?> + défense : quel(s) agent(s) ?</strong>
    L'agent attaque les agents ennemis repérés récemment dans son <?= htmlspecialchars($zoneType) ?>. L'attaque peut éliminer la cible, la capturer, échouer, ou se retourner contre l'attaquant.</li>
  <?php if ($agentLocationAttack) { ?>
  <li><strong>Attaquer le lieu : <?= $actionRoll('Attack', 'attack_location') ?> + attaque.</strong> L'agent attaque un lieu connu de son <?= htmlspecialchars($zoneType) ?>.</li>
  <?php } ?>
</ul>
<?php if (!empty($continuingActions)) { ?>
<p>Les actions : <?= htmlspecialchars(implode(', ', $continuingActions)) ?>, <strong>continuent</strong> d'un <?= htmlspecialchars($timeValue) ?> à l'autre.
Les autres retombent en « <?= htmlspecialchars(lcfirst($labelPassive)) ?> » une fois résolues.</p>
<?php } ?>

<h4 class="title is-5">Actions immédiates et évolutions</h4>
<ul>
  <li><strong>Déménager</strong> : l'agent change de <?= htmlspecialchars($zoneType) ?> tout de suite, et son action repasse à « <?= htmlspecialchars(lcfirst($labelPassive)) ?> ».</li>
  <li><strong>Donner</strong> : l'agent passe définitivement au service d'une autre faction.</li>
  <li><strong>Évolutions</strong> : avec l'ancienneté, un agent apprend de nouvelles maîtrises. Certains équipements ne sont disponibles que dans un <?= htmlspecialchars($zoneType) ?> que vous tenez.</li>
  <li>Un agent capturé devient votre <strong>prisonnier</strong> : vous pouvez le relâcher ou le transférer à une autre faction.</li>
</ul>

<h4 class="title is-5">Lire les rapports</h4>
<?= guideCaptureFigure($captureSet, 'rapport', "Le rapport d'un agent") ?>
<p>Le rapport de chaque agent est classé par <?= htmlspecialchars($timeValue) ?>, et conservé d'un <?= htmlspecialchars($timeValue) ?> à l'autre :
il forme l'histoire de l'agent. Chaque <?= htmlspecialchars($timeValue) ?> peut contenir :</p>
<ul>
  <li><strong>Changements</strong> : la vie de l'agent (recrutement, don, capture…), ses déménagements, ses valeurs du <?= htmlspecialchars($timeValue) ?> et l'action qu'il a menée.</li>
  <li><strong>Attaques</strong> : les combats qu'il a livrés ou subis, et leur issue.</li>
  <?php if ($agentLocationAttack) { ?>
  <li><strong>Attaque de lieu</strong> : ses duels lors de l'assaut ou de la défense d'un lieu, et l'issue de l'assaut.</li>
  <?php } ?>
  <li><strong>Mes investigations</strong> : l'action faite et le <?= htmlspecialchars($zoneType) ?> où elle a eu lieu, puis les autres agents qu'il a repérés et ce qu'il en a appris.
    Un agent déjà connu n'est rappelé que brièvement ; les informations nouvelles sont affichées intégralement.</li>
  <li><strong>Mes recherches</strong> : la bannière sous laquelle est le <?= htmlspecialchars($zoneType) ?>, le réseau qui le défend si l'agent a su le voir, et les lieux découverts.</li>
  <?php if ($claimEnabled) { ?>
  <li><strong>Contrôle</strong> : le résultat de sa revendication, ou les revendications dont il a été témoin.</li>
  <?php } ?>
</ul>
</div>
</div>

<div class="factions guide-band"><div class="box guide-section">
<h3 class="title is-4">4. Votre faction</h3>
<?= guideCaptureFigure($captureSet, 'faction', 'La page de votre faction') ?>
<ul>
  <li><strong>Votre base</strong> : la construire ou la déménager. Elle permet de recruter, et ses secrets ne se révèlent qu'à un enquêteur assez fort.</li>
  <li>Le coût de chaque opération est affiché à côté de son bouton.</li>
</ul>
<?= guideCaptureFigure($captureSet, 'lieux', $ownedArtefactsTitle . ' et vos lieux') ?>
<ul>
  <li><strong><?= htmlspecialchars($ownedArtefactsTitle) ?></strong> : les personnes ou les objets d'intérêt politique sous votre garde,
    et le lieu où ils se trouvent. Pour en remettre un.e à une autre faction, demandez à l'orga. Une attaque réussie sur un lieu
    rapporte ce qu'il abrite.</li>
  <li><strong>Vos lieux découverts</strong>, classés par <?= htmlspecialchars($zoneType) ?> : les lieux que vos agents ont trouvés, et ce qu'ils en ont appris.</li>
  <li><strong>Vos lieux secrets</strong>, classés par <?= htmlspecialchars($zoneType) ?> : la description de votre base et de son contenu, et les autres lieux que vous possédez.</li>
  <li><?php if ($agentLocationAttack) { ?>Pour attaquer ou défendre un lieu, donnez-en l'ordre à vos agents.<?php } else { ?>Un lieu découvert et destructible peut être attaqué depuis cette page, si vous avez une base.<?php } ?>
    Un lieu détruit peut être réparé.</li>
</ul>
<?= guideCaptureFigure($captureSet, 'informations', 'Donner des informations à une autre faction') ?>
<p><strong>Donner des informations à une autre faction :</strong></p>
<ul>
  <li><strong>Sur un agent repéré</strong> : son nom, pour que cette faction puisse l'attaquer. Il lui faut un agent dans le même <?= htmlspecialchars($zoneType) ?>.</li>
  <li><strong>Sur un lieu découvert</strong> : ce que vous en savez.</li>
</ul>
<?php if ($ressourcesEnabled) { ?>
<?= guideCaptureFigure($captureSet, 'ressources', 'Les ressources de la faction') ?>
<p>La page <strong>Ressources</strong> montre ce que possède votre faction, les règles d'acquisition de ces ressources, et l'estimation des gains pour le <?= htmlspecialchars($timeValue) ?> suivant. Vous pouvez y faire un don à une autre faction.</p>
<?php } ?>
</div>
</div>

<div class="zones guide-band"><div class="box guide-section">
<h3 class="title is-4">5. Les <?= htmlspecialchars($zoneType) ?>s</h3>
<?= guideCaptureFigure($captureSet, 'zones', 'La liste des ' . $zoneType . 's') ?>
<ul>
  <li>La <strong>carte</strong> se déplie en haut de la page.</li>
  <li>Chaque <?= htmlspecialchars($zoneType) ?> montre la <strong>bannière</strong> qui y est levée, et l'étiquette <strong>Sous notre contrôle</strong> quand il est à vous.</li>
  <li>Échanger un <?= htmlspecialchars($zoneType) ?> avec une autre faction se négocie… puis se demande à l'orga.</li>
</ul>
<?= guideCaptureFigure($captureSet, 'zone', 'Un ' . $zoneType . ' déplié') ?>
<p><strong>Cliquer sur un <?= htmlspecialchars($zoneType) ?> le déplie</strong>, et montre ce que votre faction en sait :</p>
<ul>
  <li><strong>Sa description</strong>, connue de tous.</li>
  <li><strong>Vos lieux secrets</strong> : vos lieux dans ce <?= htmlspecialchars($zoneType) ?>, leurs secrets et ce qu'ils abritent.</li>
  <li><strong>Lieux découverts</strong> : les lieux que vos agents y ont trouvés, et ce que vous en avez appris.
    <?php if (!$agentLocationAttack) { ?>Un lieu destructible peut y être attaqué, si vous avez une base.<?php } ?></li>
  <li><strong>Nos agents présents</strong>, avec leur action repliés.</li>
  <li><strong>Agents ennemis repérés</strong> : ceux que vos agents ont vus récemment, regroupés par réseau quand il est connu ; les plus anciens sont repliés.</li>
</ul>
</div>
</div>

<div class="box guide-section">
<h3 class="title is-4">6. Pour les curieux : les règles du jeu</h3>
<details>
<summary>Comment sont calculées les valeurs d'un agent</summary>
<p>Chaque agent a trois valeurs, recalculées à chaque fin de <?= htmlspecialchars($timeValue) ?> : <strong>enquête</strong>,
<strong>attaque</strong> et <strong>défense</strong>. Chacune additionne :</p>
<ul>
  <li>les bonus de ses capacités ;</li>
  <li>pour l'action active du <?= htmlspecialchars($timeValue) ?>, un dé de <?= $minRoll ?> à <?= $maxRoll ?> ; sinon une valeur fixe de <?= $passiveVal ?> ;</li>
  <li>un bonus quand votre faction tient le <?= htmlspecialchars($zoneType) ?> ;</li>
  <li>un bonus propre à certaines actions (se cacher, défendre un lieu).</li>
</ul>
<p>Les valeurs du <?= htmlspecialchars($timeValue) ?> sont écrites dans le rapport de l'agent. Plus un agent est ancien,
plus il a pu apprendre de maîtrises, et plus ses valeurs sont hautes.</p>
</details>

<details>
<summary>Ce qu'apprend une enquête sur un agent</summary>
<p>Un agent qui <?= htmlspecialchars($investigatingText) ?> observe tous les agents des autres factions présents dans son <?= htmlspecialchars($zoneType) ?>.
Pour chacun, on compare <strong>son enquête à l'enquête de l'agent observé</strong> : c'est pourquoi se cacher, qui augmente l'enquête, rend plus discret.
Selon l'écart (enquête de l'agent actif - enquête de l'agent observé), le rapport révèle, de la plus facile à la plus difficile à obtenir :</p>
<ul>
  <li>écart d'au moins <strong><?= $configInt('REPORTDIFF0') ?></strong> : son nom, son métier, son action en cours, et son origine s'il vient d'ailleurs ;</li>
  <li>écart d'au moins <strong><?= $configInt('REPORTDIFF1') ?></strong> : ses autres capacités, et ce que son contrôleur lui a appris ;</li>
  <li>écart d'au moins <strong><?= $configInt('REPORTDIFF2') ?></strong> : le numéro de son réseau, ses autres maîtrises et ses équipements les mieux cachés ;</li>
  <li>écart d'au moins <strong><?= $configInt('REPORTDIFF3') ?></strong> : le nom de la faction qui dirige ce réseau.</li>
</ul>
<p>Ce qui est appris reste acquis à votre faction et peut être donné à une autre. Un agent repéré peut être attaqué tant qu'il a été vu récemment.</p>
</details>

<details>
<summary>Ce qu'apprend une recherche sur un lieu</summary>
<p>Chaque lieu secret a une <strong>difficulté de découverte</strong>. Pour une base, elle grandit avec les capacités de la faction qui la possède,
le nombre de ses agents dans le <?= htmlspecialchars($zoneType) ?> et son ancienneté. Selon l'écart (enquête de l'agent actif - difficulté de découverte) entre l'enquête de l'agent et cette difficulté :</p>
<ul>
  <li>écart d'au moins <strong><?= $configInt('LOCATIONNAMEDIFF') ?></strong> : le nom du lieu, sans plus ; il n'est pas retenu pour la suite ;</li>
  <li>écart d'au moins <strong><?= $configInt('LOCATIONINFORMATIONDIFF') ?></strong> : sa description, s'il peut être détruit, et depuis quand il est là ou en ruine. Le lieu rejoint alors vos lieux découverts ;</li>
  <li>écart d'au moins <strong><?= $configInt('LOCATIONARTEFACTSDIFF') ?></strong> : ses secrets, et ce qu'il abrite.</li>
</ul>
<p>La recherche dit aussi sous quelle bannière est le <?= htmlspecialchars($zoneType) ?>. Si l'enquête de l'agent dépasse la défense du <?= htmlspecialchars($zoneType) ?>,
elle révèle le numéro du réseau qui le tient, et, avec une marge de plus de 2, le nom de sa faction.</p>
</details>

<details>
<summary> <strong>Le combat entre agents</strong></summary>
<p>Les attaquants agissent dans l'ordre de leur enquête, le plus perspicace en premier, et chacun attaque ses cibles dans le même ordre.
Chaque duel se résoud par <strong>l'attaque de l'attaquant</strong> à laquelle on soustrait <strong>la défense de sa cible</strong>. Il y a cinq issues possibles :</p>
<ul>
  <li><i>Capture</i> — écart d'au moins <strong><?= $configInt('ATTACKDIFF1') ?></strong> : la cible est prise vivante et devient votre prisonnier ; vous accédez à ses rapports.</li>
  <li><i>Réussite</i> — écart d'au moins <strong><?= $configInt('ATTACKDIFF0') ?></strong> : la cible est éliminée.</li>
  <li><i>Échec</i> — écart plus faible : l'attaque échoue et la cible apprend qui l'a attaquée.</li>
  <?php if ($riposteActive) { ?>
  <li><i>Défaite totale</i> — une cible qui survit riposte : si son attaque dépasse la défense de l'attaquant d'au moins <strong><?= $configInt('RIPOSTDIFF') ?></strong>, l'attaquant disparaît.</li>
  <?php } ?>
  <li><i>Introuvable</i> — la cible a déjà été éliminée plus tôt dans la même fin de <?= htmlspecialchars($timeValue) ?>.</li>
</ul>
</details>

<?php if ($claimEnabled) { ?>
<details>
<summary>La revendication d'un <?= htmlspecialchars($zoneType) ?></summary>
<?php if ($claimMode === 'worker') { ?>
<p>Chaque agent qui revendique tente sa chance <strong>seul</strong>, contre la défense du <?= htmlspecialchars($zoneType) ?> :
sa défense propre, plus un point par agent de la faction qui le tient. Il l'emporte si :</p>
<ul>
  <li>son <strong>enquête</strong> dépasse cette défense d'au moins <strong><?= $configInt('DISCRETECLAIMDIFF') ?></strong> : une prise discrète ;</li>
  <li>ou son <strong>attaque</strong> la dépasse d'au moins <strong><?= $configInt('VIOLENTCLAIMDIFF') ?></strong> : une prise par la force.</li>
</ul>
<p>Les revendicateurs d'un même <?= htmlspecialchars($zoneType) ?> sont pris dans l'ordre de leur attaque, et le premier qui réussit l'emporte.
Les agents présents sont témoins de la revendication, sauf pour une prise discrète par un revendicateur seul sur un <?= htmlspecialchars($zoneType) ?> que personne ne tenait.</p>
<?php } else { ?>
<p>Les agents d'une même faction qui revendiquent le même <?= htmlspecialchars($zoneType) ?> agissent <strong>ensemble</strong>, menés par le plus fort d'entre eux.
Il n'y a pas de dé : la valeur de revendication de la faction additionne ses agents présents, ses lieux dans le <?= htmlspecialchars($zoneType) ?>,
chaque revendicateur en plus du meneur, et un bonus quand sa bannière flotte déjà sur le <?= htmlspecialchars($zoneType) ?> sans qu'elle le tienne.</p>
<p>En face, la défense du <?= htmlspecialchars($zoneType) ?> additionne les agents et les lieux de la faction qui le tient, et chacun de ses agents qui le protège ;
un <?= htmlspecialchars($zoneType) ?> que personne ne tient a une défense fixe de <strong><?= $configInt('noControllerZoneDefenceBonus') ?></strong>.
La revendication réussit si l'écart atteint <strong><?= $configInt('claimDiff') ?></strong>. Si plusieurs factions réussissent, celle dont le meneur est le plus fort l'emporte.</p>
<?php } ?>
<p>Vous choisissez au nom de qui le <?= htmlspecialchars($zoneType) ?> est revendiqué : la bannière levée peut être celle d'une autre faction, ou aucune.
La faction qui tient vraiment le <?= htmlspecialchars($zoneType) ?> reste la vôtre.</p>
</details>
<?php } ?>

<details>
<summary>L'attaque et la défense d'un lieu</summary>
<?php if ($agentLocationAttack) { ?>
<p>Ce sont vos <strong>agents</strong> qui attaquent ou défendent un lieu de leur <?= htmlspecialchars($zoneType) ?>. En fin de <?= htmlspecialchars($timeValue) ?>, pour chaque lieu visé :</p>
<ul>
  <li>attaquants et défenseurs s'engagent dans l'ordre de leur enquête, le plus perspicace en premier ;</li>
  <li>ils s'affrontent en une suite de duels, chacun résolu comme un combat entre agents : un attaquant enchaîne les défenseurs tant qu'il gagne, un défenseur tient tant qu'il survit ;</li>
  <li>le lieu tombe si les attaquants survivants sont <strong>strictement plus nombreux</strong> que
    <?php if ($overwhelmMorethan) { ?>les défenseurs survivants plus <strong><?= $configInt('locationOverwhelmValue') ?></strong><?php } else { ?><strong><?= $configInt('locationOverwhelmValue') ?></strong> fois les défenseurs survivants<?php } ?>.
    Personne contre personne, le lieu tient.</li>
</ul>
<p>Pour emporter ce que le lieu abrite, il faut posséder au moins un lieu destructible où le mener : sans cela, le lieu reste debout malgré la victoire.</p>
<?php } else { ?>
<p>C'est votre <strong>faction</strong> qui attaque un lieu découvert et destructible, depuis la page de votre faction, à condition d'avoir une base.
<?= $locationAttackMode === 'endTurn' ? "L'attaque est préparée pendant le " . htmlspecialchars($timeValue) . ', avec une prédiction de son issue, et résolue en fin de ' . htmlspecialchars($timeValue) . '.' : "L'attaque est résolue immédiatement." ?></p>
<p>L'<strong>attaque</strong> de votre faction additionne une valeur de base, ses capacités, ses agents présents dans le <?= htmlspecialchars($zoneType) ?>,
et un point si votre bannière y flotte ou si vous le tenez. La <strong>défense</strong> du lieu additionne de même une valeur de base, les capacités
et les agents de la faction qui le possède, un point pour la bannière ou le contrôle du <?= htmlspecialchars($zoneType) ?>, et son ancienneté
(jusqu'à <strong>+<?= $configInt('maxBonusDefenceTurns') ?></strong>) ; un lieu qui n'appartient à personne reçoit <strong>+<?= $configInt('noControllerDefenceBonus') ?></strong>.
L'attaque réussit si elle dépasse la défense d'au moins <strong><?= $configInt('attackLocationDiff') ?></strong>.</p>
<?php } ?>
<p>Un lieu pris est détruit, ou seulement pillé s'il est indestructible, et ce qu'il abrite revient au vainqueur. Un lieu détruit peut être réparé.</p>
</details>
</div>

<div class="box guide-section">
<h3 class="title is-4">Pour les futurs organisateurs</h3>
<details>
<summary>Configuration, débogage et contact</summary>
<p>Presque tous les paramètres du système sont <strong>configurables</strong> dans la table <code>config</code> : valeurs, textes,
modes de calcul, seuils de découverte, résultats des attaques… Le détail de chaque clé est dans le
<a href="/<?= htmlspecialchars($folder) ?>/base/docConfig.php"><strong>guide de configuration</strong></a>.</p>
<p>Le mode <code>DEBUG_REPORT</code> affiche l'intégralité des calculs de chaque fin de <?= htmlspecialchars($timeValue) ?>, pour le suivi du développement ou l'équilibrage.</p>
<p>Les sources sont libres sur <a href="https://github.com/Edward-Croc/RPGConquestGame" target="_blank">GitHub</a>,
où vous pouvez déclarer les bugs dans les <a href="https://github.com/Edward-Croc/RPGConquestGame/issues" target="_blank">issues</a>.
Et en dernier recours, voici <details><summary>mon email :</summary><p>lenainblanc[at]wanadoo.fr</p></details></p>
</details>
</div>
</div>
