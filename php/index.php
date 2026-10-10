<?php
require_once __DIR__ . '/representatives.php';

$db = get_db();

$reps = get_all_representatives('district');
$total = count($reps);

function facet_counts(PDO $db, string $col): array {
    return $db->query("SELECT `$col` as val, COUNT(*) as cnt
                       FROM representatives
                       WHERE `$col` != '' AND is_current = 1
                       GROUP BY `$col`
                       ORDER BY cnt DESC, val ASC")->fetchAll();
}

$party_facets    = facet_counts($db, 'party');
$religion_facets = facet_counts($db, 'religion');
$city_facets     = facet_counts($db, 'city');

$mil_yes = (int)$db->query("SELECT COUNT(*) FROM representatives WHERE military != '' AND is_current = 1")->fetchColumn();
$mil_no  = $total - $mil_yes;

// Recreational interests — split each row on ", " and count individual values
$rec_counts = [];
$rec_res = $db->query("SELECT recreational_interests FROM representatives WHERE recreational_interests != '' AND is_current = 1");
foreach ($rec_res->fetchAll(PDO::FETCH_COLUMN) as $interests) {
    // array_unique so one person listing "fishing" twice only counts once
    $items = array_unique(array_filter(array_map(
        fn($item) => strtolower(trim($item)),
        explode(',', $interests)
    )));
    foreach ($items as $key) {
        $rec_counts[$key] = ($rec_counts[$key] ?? 0) + 1;
    }
}
arsort($rec_counts); // highest count first

// Education — extract institution names containing "University"
function universities(string $education): array {
    $unis = [];
    foreach (explode(';', $education) as $entry) {
        $inst = trim(explode(',', $entry)[0]);
        if (stripos($inst, 'University') !== false) {
            $unis[] = $inst;
        }
    }
    // Two degrees from the same school should only count that person once
    return array_unique($unis);
}

$uni_counts = [];
$edu_res = $db->query("SELECT education FROM representatives WHERE education != '' AND is_current = 1");
foreach ($edu_res->fetchAll(PDO::FETCH_COLUMN) as $education) {
    foreach (universities($education) as $institution) {
        $uni_counts[$institution] = ($uni_counts[$institution] ?? 0) + 1;
    }
}
arsort($uni_counts);

$party_letters = ['republican' => 'R', 'democrat' => 'D'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Florida House of Representatives</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <img src="https://www.flhouse.gov/Images/svg/house_seal.svg" alt="Florida House Seal" class="seal">
    <div>
      <h1>Florida House of Representatives</h1>
      <p id="result-count"><?= $total ?> Members &mdash; 2024&ndash;2026 Session</p>
    </div>
  </div>
</header>

<!-- ── Search + filter bar ───────────────────────────────────────────── -->
<div class="search-bar-wrap">
  <div class="search-bar">

    <div class="search-field search-field--wide">
      <label for="q">Search</label>
      <input id="q" type="search" placeholder="Name, occupation, education…" autocomplete="off">
    </div>

    <div class="search-field search-field--sm">
      <label for="f-district">District</label>
      <input id="f-district" type="number" min="1" max="120" placeholder="1–120">
    </div>

    <button id="filter-toggle" class="filter-toggle-btn" aria-expanded="false" aria-controls="facet-panel">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15">
        <line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/>
      </svg>
      Filters
      <span class="filter-badge" id="filter-badge" hidden>0</span>
      <span class="filter-arrow" id="filter-arrow">▾</span>
    </button>

    <button id="reset-btn" type="button">Clear</button>
  </div>

  <!-- Active filter chips -->
  <div class="chips-row" id="chips-row" hidden>
    <div class="chips" id="chips"></div>
  </div>
</div>

<!-- ── Grid ──────────────────────────────────────────────────────────── -->
<main class="grid" id="grid">
  <p class="no-results" id="no-results" hidden>No representatives match your search.</p>

  <?php foreach ($reps as $rep): ?>
    <?php
      $party_class  = strtolower($rep['party']);
      $photo        = htmlspecialchars($rep['photo_url']);
      $name         = htmlspecialchars($rep['full_name']);
      $url          = 'profile.php?id=' . (int)$rep['member_id'];
      $religion_val = strtolower($rep['religion'] ?? '');
      $city_val     = strtolower($rep['city'] ?? '');
      $mil_val      = !empty($rep['military']) ? 'yes' : 'no';
      $rec_val      = strtolower($rep['recreational_interests'] ?? '');
      // Extract universities (pipe-separated so commas in names stay intact)
      $edu_val = strtolower(implode('|', universities($rep['education'] ?? '')));
      $searchable   = strtolower(implode(' ', [
        $rep['full_name'], $rep['city'], $rep['occupation'],
        $rep['birthplace'], $rep['counties'], $rep['education'],
        $rep['spouse'], $rep['children'], $rep['religion'],
        $rep['military'], $rep['recreational_interests'],
      ]));
    ?>
    <article class="card <?= $party_class ?>"
             data-party="<?= $party_class ?>"
             data-district="<?= (int)$rep['district'] ?>"
             data-city="<?= htmlspecialchars($city_val) ?>"
             data-religion="<?= htmlspecialchars($religion_val) ?>"
             data-military="<?= $mil_val ?>"
             data-recreational="<?= htmlspecialchars($rec_val) ?>"
             data-education="<?= htmlspecialchars($edu_val) ?>"
             data-search="<?= htmlspecialchars($searchable) ?>">
      <a href="<?= $url ?>">
        <div class="card-photo">
          <img src="<?= $photo ?>" alt="<?= $name ?>" loading="lazy"
               onerror="this.src='placeholder.svg'">
          <span class="party-badge <?= $party_class ?>">
            <?= $party_letters[$party_class] ?? 'NPA' ?>
          </span>
        </div>
        <div class="card-body">
          <h2><?= $name ?></h2>
          <p class="district">District <?= (int)$rep['district'] ?></p>
          <p class="city"><?= htmlspecialchars($rep['city'] ?: '—') ?></p>
        </div>
      </a>
    </article>
  <?php endforeach; ?>
</main>

<!-- ── Facet panel — lives at body level so nothing clips it ─────────── -->
<div class="facet-panel" id="facet-panel" hidden role="dialog" aria-label="Filter options">

  <div class="facet-body">
    <nav class="facet-cats" id="facet-cats">
      <div class="facet-cat active" data-facet="party"    tabindex="0">Party</div>
      <div class="facet-cat"        data-facet="religion" tabindex="0">Religion</div>
      <div class="facet-cat"        data-facet="city"     tabindex="0">City</div>
      <div class="facet-cat"        data-facet="military"        tabindex="0">Military Service</div>
      <div class="facet-cat"        data-facet="recreational"    tabindex="0">Recreational Interests</div>
      <div class="facet-cat"        data-facet="education"       tabindex="0">University</div>
    </nav>

    <div class="facet-opts-wrap" id="facet-opts-wrap">

      <!-- Party -->
      <div class="facet-opts" data-facet="party">
        <?php foreach ($party_facets as $row): ?>
        <label class="facet-opt">
          <input type="checkbox" data-facet="party" value="<?= strtolower(htmlspecialchars($row['val'])) ?>">
          <span class="facet-opt-label"><?= htmlspecialchars($row['val']) ?></span>
          <span class="facet-opt-count"><?= $row['cnt'] ?></span>
        </label>
        <?php endforeach; ?>
      </div>

      <!-- Religion -->
      <div class="facet-opts" data-facet="religion" hidden>
        <div class="facet-search-wrap">
          <input type="search" class="facet-inner-search" placeholder="Search religion…" aria-label="Search religion options">
        </div>
        <div class="facet-scroll">
          <?php foreach ($religion_facets as $row): ?>
          <label class="facet-opt">
            <input type="checkbox" data-facet="religion" value="<?= strtolower(htmlspecialchars($row['val'])) ?>">
            <span class="facet-opt-label"><?= htmlspecialchars($row['val']) ?></span>
            <span class="facet-opt-count"><?= $row['cnt'] ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- City -->
      <div class="facet-opts" data-facet="city" hidden>
        <div class="facet-search-wrap">
          <input type="search" class="facet-inner-search" placeholder="Search city…" aria-label="Search city options">
        </div>
        <div class="facet-scroll">
          <?php foreach ($city_facets as $row): ?>
          <label class="facet-opt">
            <input type="checkbox" data-facet="city" value="<?= strtolower(htmlspecialchars($row['val'])) ?>">
            <span class="facet-opt-label"><?= htmlspecialchars($row['val']) ?></span>
            <span class="facet-opt-count"><?= $row['cnt'] ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Military -->
      <div class="facet-opts" data-facet="military" hidden>
        <label class="facet-opt">
          <input type="checkbox" data-facet="military" value="yes">
          <span class="facet-opt-label">Has military service</span>
          <span class="facet-opt-count"><?= $mil_yes ?></span>
        </label>
        <label class="facet-opt">
          <input type="checkbox" data-facet="military" value="no">
          <span class="facet-opt-label">No military service</span>
          <span class="facet-opt-count"><?= $mil_no ?></span>
        </label>
      </div>

      <!-- Recreational Interests -->
      <div class="facet-opts" data-facet="recreational" hidden>
        <div class="facet-search-wrap">
          <input type="search" class="facet-inner-search" placeholder="Search interests…" aria-label="Search recreational interest options">
        </div>
        <div class="facet-scroll">
          <?php foreach ($rec_counts as $interest => $cnt): ?>
          <label class="facet-opt">
            <input type="checkbox" data-facet="recreational" value="<?= htmlspecialchars($interest) ?>">
            <span class="facet-opt-label"><?= htmlspecialchars(ucfirst($interest)) ?></span>
            <span class="facet-opt-count"><?= $cnt ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- University / Education -->
      <div class="facet-opts" data-facet="education" hidden>
        <div class="facet-search-wrap">
          <input type="search" class="facet-inner-search" placeholder="Search university…" aria-label="Search university options">
        </div>
        <div class="facet-scroll">
          <?php foreach ($uni_counts as $uni => $cnt): ?>
          <label class="facet-opt">
            <input type="checkbox" data-facet="education" value="<?= htmlspecialchars(strtolower($uni)) ?>">
            <span class="facet-opt-label"><?= htmlspecialchars($uni) ?></span>
            <span class="facet-opt-count"><?= $cnt ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /facet-opts-wrap -->
  </div><!-- /facet-body -->

  <div class="facet-footer">
    <button class="facet-clear-all" id="facet-clear-all">Clear all filters</button>
  </div>

</div><!-- /facet-panel -->

<script>
(() => {
  const TOTAL  = <?= $total ?>;
  const cards  = Array.from(document.querySelectorAll('.card'));

  const state = {
    q: '', district: '',
    facets: { party: new Set(), religion: new Set(), city: new Set(), military: new Set(), recreational: new Set(), education: new Set() },
  };

  const qInput      = document.getElementById('q');
  const districtEl  = document.getElementById('f-district');
  const resetBtn    = document.getElementById('reset-btn');
  const counter     = document.getElementById('result-count');
  const noResults   = document.getElementById('no-results');
  const chipsRow    = document.getElementById('chips-row');
  const chipsEl     = document.getElementById('chips');
  const badge       = document.getElementById('filter-badge');
  const toggleBtn   = document.getElementById('filter-toggle');
  const facetPanel  = document.getElementById('facet-panel');
  const facetArrow  = document.getElementById('filter-arrow');
  const clearAllBtn = document.getElementById('facet-clear-all');

  // ── Position panel below the Filters button ──────────────────────────
  function positionPanel() {
    const r = toggleBtn.getBoundingClientRect();
    const panelW = 480;
    const margin = 8;

    // Align left edge to button; shift left if it would overflow the viewport
    let left = r.left;
    if (left + panelW > window.innerWidth - margin) {
      left = window.innerWidth - panelW - margin;
    }
    left = Math.max(margin, left);

    facetPanel.style.top  = (r.bottom + 6) + 'px';
    facetPanel.style.left = left + 'px';
  }

  // ── Open / close ─────────────────────────────────────────────────────
  function openPanel() {
    positionPanel();
    facetPanel.hidden = false;
    toggleBtn.setAttribute('aria-expanded', 'true');
    facetArrow.textContent = '▴';
  }
  function closePanel() {
    facetPanel.hidden = true;
    toggleBtn.setAttribute('aria-expanded', 'false');
    facetArrow.textContent = '▾';
  }

  toggleBtn.addEventListener('click', e => {
    e.stopPropagation();
    facetPanel.hidden ? openPanel() : closePanel();
  });

  document.addEventListener('click', e => {
    if (!facetPanel.contains(e.target) && e.target !== toggleBtn) closePanel();
  });

  window.addEventListener('resize', () => { if (!facetPanel.hidden) positionPanel(); });
  window.addEventListener('scroll', () => { if (!facetPanel.hidden) positionPanel(); }, { passive: true });

  // ── Category hover / click ───────────────────────────────────────────
  document.querySelectorAll('.facet-cat').forEach(cat => {
    const activate = () => {
      document.querySelectorAll('.facet-cat').forEach(c => c.classList.remove('active'));
      document.querySelectorAll('.facet-opts').forEach(o => o.hidden = true);
      cat.classList.add('active');
      const opts = document.querySelector(`.facet-opts[data-facet="${cat.dataset.facet}"]`);
      if (opts) opts.hidden = false;
    };
    cat.addEventListener('mouseenter', activate);
    cat.addEventListener('click', activate);
    cat.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') activate(); });
  });

  // ── Facet checkboxes ─────────────────────────────────────────────────
  document.querySelectorAll('.facet-opt input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', () => {
      if (cb.checked) state.facets[cb.dataset.facet].add(cb.value);
      else            state.facets[cb.dataset.facet].delete(cb.value);
      applyFilters();
    });
  });

  // ── Inner search (city / religion) ───────────────────────────────────
  document.querySelectorAll('.facet-inner-search').forEach(input => {
    input.addEventListener('input', () => {
      const q = input.value.toLowerCase();
      input.closest('.facet-opts').querySelectorAll('.facet-opt').forEach(opt => {
        opt.hidden = q !== '' && !opt.querySelector('.facet-opt-label').textContent.toLowerCase().includes(q);
      });
    });
  });

  // ── Filtering ────────────────────────────────────────────────────────
  // Multi-value facets: maps facet name → separator used in data attribute
  const multiValueFacets = { recreational: ',', education: '|' };

  function cardMatches(card) {
    const d = card.dataset;
    if (state.q && !d.search.includes(state.q)) return false;
    if (state.district && d.district !== state.district) return false;
    for (const [facet, vals] of Object.entries(state.facets)) {
      if (vals.size === 0) continue;
      if (facet in multiValueFacets) {
        const sep = multiValueFacets[facet];
        const cardVals = (d[facet] || '').split(sep).map(s => s.trim()).filter(Boolean);
        if (![...vals].some(v => cardVals.includes(v))) return false;
      } else {
        if (!vals.has(d[facet])) return false;
      }
    }
    return true;
  }

  function applyFilters() {
    let visible = 0;
    cards.forEach(c => { const show = cardMatches(c); c.hidden = !show; if (show) visible++; });
    counter.textContent = visible === TOTAL
      ? `${TOTAL} Members — 2024–2026 Session`
      : `${visible} of ${TOTAL} members`;
    noResults.hidden = visible > 0;
    renderChips();
    updateBadge();
  }

  // ── Chips ────────────────────────────────────────────────────────────
  const facetLabels = { party:'Party', religion:'Religion', city:'City', military:'Military', recreational:'Interests', education:'University' };

  function renderChips() {
    chipsEl.innerHTML = '';
    let any = false;
    if (state.q) {
      any = true;
      chipsEl.appendChild(makeChip(`"${state.q}"`, () => { state.q = ''; qInput.value = ''; applyFilters(); }));
    }
    if (state.district) {
      any = true;
      chipsEl.appendChild(makeChip(`District ${state.district}`, () => { state.district = ''; districtEl.value = ''; applyFilters(); }));
    }
    for (const [facet, vals] of Object.entries(state.facets)) {
      vals.forEach(val => {
        any = true;
        const cb = document.querySelector(`input[data-facet="${facet}"][value="${val}"]`);
        const label = cb ? cb.nextElementSibling.textContent : val;
        chipsEl.appendChild(makeChip(`${facetLabels[facet]}: ${label}`, () => {
          vals.delete(val);
          if (cb) cb.checked = false;
          applyFilters();
        }));
      });
    }
    chipsRow.hidden = !any;
  }

  function makeChip(text, onRemove) {
    const chip = document.createElement('span');
    chip.className = 'chip';
    chip.textContent = text;
    const x = document.createElement('button');
    x.textContent = '×'; x.className = 'chip-remove';
    x.setAttribute('aria-label', `Remove filter: ${text}`);
    x.addEventListener('click', onRemove);
    chip.appendChild(x);
    return chip;
  }

  function updateBadge() {
    const count = [...Object.values(state.facets)].reduce((n, s) => n + s.size, 0) + (state.district ? 1 : 0);
    badge.textContent = count;
    badge.hidden = count === 0;
  }

  // ── Global clear ─────────────────────────────────────────────────────
  resetBtn.addEventListener('click', () => {
    state.q = ''; state.district = '';
    qInput.value = ''; districtEl.value = '';
    Object.values(state.facets).forEach(s => s.clear());
    document.querySelectorAll('.facet-opt input').forEach(cb => cb.checked = false);
    applyFilters();
  });

  clearAllBtn.addEventListener('click', () => {
    Object.values(state.facets).forEach(s => s.clear());
    document.querySelectorAll('.facet-opt input').forEach(cb => cb.checked = false);
    applyFilters();
  });

  qInput.addEventListener('input',     () => { state.q = qInput.value.trim().toLowerCase(); applyFilters(); });
  districtEl.addEventListener('input', () => { state.district = districtEl.value.trim(); applyFilters(); });

})();
</script>

</body>
</html>
