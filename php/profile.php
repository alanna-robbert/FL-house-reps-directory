<?php
require_once __DIR__ . '/representatives.php';

$member_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$member_id) {
    header('Location: index.php');
    exit;
}

$rep = get_by_member_id($member_id);
if (!$rep) {
    http_response_code(404);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Not Found | Florida House</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="profile-page">
<div class="profile-back">
  <a href="index.php">&larr; All Representatives</a>
</div>
<main class="profile-container">
  <section class="profile-main">
    <h1>Representative not found</h1>
    <p>We couldn't find a representative with that ID.</p>
  </section>
</main>
</body>
</html>
    <?php
    exit;
}

$name      = htmlspecialchars($rep['full_name']);
$photo     = htmlspecialchars($rep['photo_url']);
$party_cls = strtolower($rep['party']);

// Build bio rows — only show fields that have a value
$bio_rows = [
    // Only shown for people who left office mid-term
    'Former Member — Served'  => $rep['is_current'] ? '' : $rep['term'],
    'City of Residence'       => $rep['city'],
    'Occupation'              => $rep['occupation'],
    'District'                => $rep['district'] ? 'District ' . $rep['district'] : '',
    'Counties'                => $rep['counties'],
    'Born'                    => $rep['birthplace'],
    'Age'                     => $rep['age'],
    'Moved to Florida'        => $rep['year_moved_to_florida'],
    'Spouse'                  => $rep['spouse'],
    'Children'                => $rep['children'],
    'Grandchildren'           => $rep['grandchildren'],
    'Education'               => $rep['education'],
    'Military Service'        => $rep['military'],
    'Religious Affiliation'   => $rep['religion'],
    'Recreational Interests'  => $rep['recreational_interests'],
];

// Extra bio fields (leadership roles, history, etc.)
$extra = json_decode($rep['extra_bio'] ?? '{}', true) ?: [];
$extra_labels = [
    'speaker'                                    => 'Speaker',
    'speaker_pro_tempore'                        => 'Speaker Pro Tempore',
    'majority_leader'                            => 'Majority Leader',
    'deputy_majority_leader'                     => 'Deputy Majority Leader',
    'minority_leader'                            => 'Minority Leader',
    'minority_leader_pro_tempore'                => 'Minority Leader Pro Tempore',
    'minority_democratic_whip'                   => 'Democratic Whip',
    'minority_democratic_deputy_whip'            => 'Democratic Deputy Whip',
    'deputy_majority_leader_republican_chief_whip' => 'Republican Chief Whip',
    'history'                                    => 'History',
    'deceased'                                   => 'Note',
];
foreach ($extra as $key => $val) {
    $label = $extra_labels[$key] ?? ucwords(str_replace('_', ' ', $key));
    $bio_rows[$label] = $val;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $name ?> | Florida House</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="profile-page">

<div class="profile-back">
  <a href="index.php">&larr; All Representatives</a>
</div>

<main class="profile-container">

  <aside class="profile-sidebar <?= $party_cls ?>">
    <img src="<?= $photo ?>" alt="<?= $name ?>"
         onerror="this.src='placeholder.svg'">
    <div class="profile-party-badge <?= $party_cls ?>">
      <?= htmlspecialchars($rep['party']) ?>
    </div>
    <a class="profile-ext-link"
       href="<?= htmlspecialchars($rep['bio_url']) ?>"
       target="_blank" rel="noopener">
      Official Bio &rarr;
    </a>
  </aside>

  <section class="profile-main">
    <h1><?= $name ?></h1>
    <h2 class="profile-subtitle <?= $party_cls ?>">
      District <?= (int)$rep['district'] ?> &mdash; <?= htmlspecialchars($rep['party']) ?>
    </h2>

    <table class="bio-table">
      <?php foreach ($bio_rows as $label => $value): ?>
        <?php if (trim((string)$value) === '') continue; ?>
        <tr>
          <th><?= htmlspecialchars($label) ?></th>
          <td><?= htmlspecialchars($value) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </section>

</main>

</body>
</html>