<?php
// app/views/admin/bookings.php
$statusOptions = ['pending','confirmed','in_progress','completed','cancelled','rescheduled'];

$counts  = array_fill_keys($statusOptions, 0);
$revenue = 0;
foreach ($bookings as $b) {
    $s = $b['status'] ?? '';
    if (isset($counts[$s])) $counts[$s]++;
    if ($s === 'completed') $revenue += (float)$b['total_amount'];
}
$total = count($bookings);

$kpiConfig = [
    'total'       => ['label'=>'Total Bookings', 'val'=>$total,                'color'=>'#C9A84C'],
    'pending'     => ['label'=>'Pending',         'val'=>$counts['pending'],    'color'=>'#D97706'],
    'in_progress' => ['label'=>'In Progress',     'val'=>$counts['in_progress'],'color'=>'#EA580C'],
    'confirmed'   => ['label'=>'Confirmed',       'val'=>$counts['confirmed'],  'color'=>'#16A34A'],
    'completed'   => ['label'=>'Completed',       'val'=>$counts['completed'],  'color'=>'#2563EB'],
    'cancelled'   => ['label'=>'Cancelled',       'val'=>$counts['cancelled'],  'color'=>'#DC2626'],
    'revenue'     => ['label'=>'Revenue',         'val'=>$revenue,              'color'=>'#C9A84C'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bookings — QuickBook Admin</title>
<script>
  (function(){ var t=localStorage.getItem('qb-admin-theme')||'light'; document.documentElement.setAttribute('data-theme',t); })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin_nav.css">
<style>
/* ═══════════════════════════════════════
   BOOKINGS PAGE — Cream & Gold Theme
═══════════════════════════════════════ */
:root {
  --gold:        #C9A84C; --gold-dim:  #A88A38; --gold-bright: #E8C96A;
  --gold-lt:     rgba(201,168,76,.13); --gold-soft: rgba(201,168,76,.08);
  --gold-border: rgba(201,168,76,.25); --gold-border-md: rgba(201,168,76,.45);
  --bg-page:     #F8F4ED; --card-bg: #FFFFFF;
  --text-primary:#1C1710; --text-muted: rgba(28,23,16,.55); --text-dim: rgba(28,23,16,.38);
  --font-display:'Playfair Display',serif; --font-body:'DM Sans',sans-serif; --font-mono:'DM Mono',monospace;
  --r-sm:10px; --r-md:14px; --r-lg:18px; --r-xl:24px;
  --ease-out: cubic-bezier(.22,1,.36,1);
}
[data-theme="dark"] {
  --bg-page:#0D1117; --card-bg:rgba(18,24,38,.90);
  --text-primary:#EDE3CC; --text-muted:rgba(237,227,204,.55); --text-dim:rgba(237,227,204,.38);
  --gold-lt:rgba(201,168,76,.18); --gold-soft:rgba(201,168,76,.10);
  --gold-border:rgba(201,168,76,.28); --gold-border-md:rgba(201,168,76,.48);
}

/* ── Dark mode overrides ── */
[data-theme="dark"] body,
[data-theme="dark"] .admin-page,
[data-theme="dark"] .content { background: #0D1117 !important; }

/* Dark background blobs */
[data-theme="dark"] body::before {
  background:
    radial-gradient(ellipse 70% 55% at 0% 0%,   rgba(201,168,76,.08) 0%, transparent 60%),
    radial-gradient(ellipse 55% 45% at 100% 10%, rgba(201,140,80,.06) 0%, transparent 55%),
    radial-gradient(ellipse 60% 40% at 50% 50%,  rgba(13,17,23,.85)  0%, transparent 70%) !important;
}

/* KPI cards in dark */
[data-theme="dark"] .bk-kpi {
  background: rgba(18,24,38,.90) !important;
  border-color: rgba(201,168,76,.20) !important;
  box-shadow: 0 4px 20px rgba(0,0,0,.40) !important;
}
[data-theme="dark"] .bk-kpi:hover {
  border-color: rgba(201,168,76,.45) !important;
  box-shadow: 0 8px 32px rgba(0,0,0,.50) !important;
}

/* Panel in dark */
[data-theme="dark"] .bk-panel {
  background: rgba(16,21,34,.92) !important;
  border-color: rgba(201,168,76,.20) !important;
  box-shadow: 0 8px 32px rgba(0,0,0,.45) !important;
}
[data-theme="dark"] .bk-panel-head {
  background: linear-gradient(135deg, rgba(24,18,8,.96) 0%, rgba(16,12,4,.98) 100%) !important;
  border-bottom-color: rgba(201,168,76,.22) !important;
}
[data-theme="dark"] .bk-panel-title { color: #EDE3CC !important; }
[data-theme="dark"] .bk-panel-count {
  background: rgba(201,168,76,.12) !important;
  border-color: rgba(201,168,76,.22) !important;
  color: rgba(237,227,204,.45) !important;
}

/* Filter bar in dark */
[data-theme="dark"] .bk-filter-bar {
  background: rgba(255,255,255,.02) !important;
  border-bottom-color: rgba(201,168,76,.14) !important;
}
[data-theme="dark"] .bk-filter-btn {
  border-color: rgba(201,168,76,.22) !important;
  color: rgba(237,227,204,.45) !important;
  background: transparent !important;
}
[data-theme="dark"] .bk-filter-btn:hover {
  background: rgba(201,168,76,.08) !important;
  color: #EDE3CC !important;
}
[data-theme="dark"] .bk-filter-btn.active {
  background: rgba(201,168,76,.14) !important;
  color: #E8C96A !important;
  border-color: rgba(201,168,76,.40) !important;
}
/* Coloured active in dark */
[data-theme="dark"] .bk-filter-btn.active[data-filter="pending"]     { background:rgba(217,119,6,.18)!important;  color:#F59E0B!important; border-color:rgba(217,119,6,.45)!important; }
[data-theme="dark"] .bk-filter-btn.active[data-filter="confirmed"]   { background:rgba(22,163,74,.18)!important;  color:#4ADE80!important; border-color:rgba(22,163,74,.45)!important; }
[data-theme="dark"] .bk-filter-btn.active[data-filter="completed"]   { background:rgba(37,99,235,.18)!important;  color:#60A5FA!important; border-color:rgba(37,99,235,.45)!important; }
[data-theme="dark"] .bk-filter-btn.active[data-filter="cancelled"]   { background:rgba(220,38,38,.18)!important;  color:#F87171!important; border-color:rgba(220,38,38,.45)!important; }
[data-theme="dark"] .bk-filter-btn.active[data-filter="in_progress"] { background:rgba(234,88,12,.18)!important;  color:#FB923C!important; border-color:rgba(234,88,12,.45)!important; }
[data-theme="dark"] .bk-filter-btn.active[data-filter="rescheduled"] { background:rgba(124,58,237,.18)!important; color:#A78BFA!important; border-color:rgba(124,58,237,.45)!important; }

[data-theme="dark"] .bk-search {
  background: rgba(255,255,255,.05) !important;
  border-color: rgba(201,168,76,.22) !important;
  color: #EDE3CC !important;
}
[data-theme="dark"] .bk-search::placeholder { color: rgba(237,227,204,.30) !important; }
[data-theme="dark"] .bk-search:focus { border-color: rgba(201,168,76,.50) !important; box-shadow: 0 0 0 3px rgba(201,168,76,.10) !important; }

/* Table in dark */
[data-theme="dark"] .bk-table thead { background: rgba(255,255,255,.03) !important; }
[data-theme="dark"] .bk-table th {
  background: transparent !important;
  border-bottom-color: rgba(201,168,76,.18) !important;
  color: #A88A38 !important;
}
[data-theme="dark"] .bk-table td {
  border-bottom-color: rgba(255,255,255,.05) !important;
  color: rgba(237,227,204,.55) !important;
}
[data-theme="dark"] .bk-table tbody tr:hover { background: rgba(201,168,76,.06) !important; }
[data-theme="dark"] .bk-table tbody tr:hover td { color: #EDE3CC !important; }
[data-theme="dark"] .bk-td-name   { color: #EDE3CC !important; }
[data-theme="dark"] .bk-td-service { color: rgba(237,227,204,.85) !important; }
[data-theme="dark"] .bk-td-provider { color: rgba(237,227,204,.42) !important; }
[data-theme="dark"] .bk-td-date   { color: rgba(237,227,204,.38) !important; }
[data-theme="dark"] .bk-td-amount { color: #C9A84C !important; }
[data-theme="dark"] .bk-td-id     { color: rgba(237,227,204,.32) !important; }

/* Avatar stays gold in dark */
[data-theme="dark"] .bk-td-av {
  background: linear-gradient(135deg, #A88A38, #C9A84C) !important;
  color: #1a1200 !important;
}

/* Pills brighter in dark */
[data-theme="dark"] .adm-pill--pending     { background:rgba(217,119,6,.18)!important;  color:#F59E0B!important; border-color:rgba(217,119,6,.40)!important; }
[data-theme="dark"] .adm-pill--confirmed   { background:rgba(22,163,74,.18)!important;  color:#4ADE80!important; border-color:rgba(22,163,74,.40)!important; }
[data-theme="dark"] .adm-pill--completed   { background:rgba(37,99,235,.18)!important;  color:#60A5FA!important; border-color:rgba(37,99,235,.40)!important; }
[data-theme="dark"] .adm-pill--cancelled   { background:rgba(220,38,38,.18)!important;  color:#F87171!important; border-color:rgba(220,38,38,.40)!important; }
[data-theme="dark"] .adm-pill--in_progress { background:rgba(234,88,12,.18)!important;  color:#FB923C!important; border-color:rgba(234,88,12,.40)!important; }
[data-theme="dark"] .adm-pill--rescheduled { background:rgba(124,58,237,.18)!important; color:#A78BFA!important; border-color:rgba(124,58,237,.40)!important; }

*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
a{text-decoration:none;color:inherit}
button{font-family:inherit;cursor:pointer;border:none;background:none}

body {
  font-family: var(--font-body);
  background: var(--bg-page);
  color: var(--text-primary);
  -webkit-font-smoothing: antialiased;
  padding-left: var(--sb-w, 240px);
  min-height: 100vh;
}
[data-theme="dark"] body { background: #0D1117; }

.grain {
  pointer-events:none; position:fixed; inset:0; z-index:900;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
  opacity:.020; mix-blend-mode:multiply;
}

/* ── Page wrapper ── */
.bk-page {
  max-width: 1380px;
  margin: 0 auto;
  padding: 2rem 2rem 4rem;
  position: relative; z-index: 1;
}

/* ── Page header ── */
.bk-header {
  margin-bottom: 2rem;
}
.bk-eyebrow {
  display: flex; align-items: center; gap: .5rem;
  font-family: var(--font-mono); font-size: .62rem;
  letter-spacing: .14em; text-transform: uppercase;
  color: var(--gold-dim); margin-bottom: .55rem;
}
.bk-eyebrow-dot {
  width: 6px; height: 6px; border-radius: 99px;
  background: var(--gold);
  animation: pulse 1.8s ease infinite;
}
@keyframes pulse {
  0%,100%{opacity:1;transform:scale(1)}
  50%{opacity:.5;transform:scale(.65)}
}
.bk-title {
  font-family: var(--font-display);
  font-size: clamp(1.8rem,3vw,2.4rem);
  font-weight: 700; font-style: italic;
  color: var(--text-primary); line-height: 1.1;
  margin-bottom: .4rem;
}
.bk-title em { color: var(--gold-dim); font-style: italic; }
.bk-subtitle {
  font-size: .85rem; color: var(--text-muted);
  font-family: var(--font-body);
}

/* ── KPI Cards ── */
.bk-kpi-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: .85rem;
  margin-bottom: 2rem;
}
.bk-kpi {
  background: rgba(255,255,255,.45);
  backdrop-filter: blur(20px) saturate(1.8);
  -webkit-backdrop-filter: blur(20px) saturate(1.8);
  border: 1.5px solid rgba(255,255,255,.70);
  border-top: 1.5px solid rgba(255,255,255,.90);
  border-left: 1.5px solid rgba(255,255,255,.75);
  border-radius: var(--r-lg);
  padding: 1.2rem 1.2rem 1rem;
  position: relative; overflow: hidden;
  transition: transform .2s var(--ease-out), box-shadow .2s, border-color .2s, outline .15s;
  box-shadow: 0 4px 24px rgba(139,110,60,.10), 0 1px 0 rgba(255,255,255,.80) inset;
  cursor: pointer;
  user-select: none;
}
.bk-kpi::before {
  content: ''; position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
  background: var(--kpi-accent, var(--gold));
  border-radius: var(--r-lg) var(--r-lg) 0 0;
}
.bk-kpi::after {
  content: ''; position: absolute;
  top: 0; right: 0; width: 70px; height: 70px;
  background: radial-gradient(ellipse at top right, var(--kpi-glow, rgba(201,168,76,.10)) 0%, transparent 70%);
  pointer-events: none;
}
.bk-kpi:hover {
  transform: translateY(-4px);
  background: rgba(255,255,255,.62);
  border-color: rgba(255,255,255,.88);
  box-shadow: 0 12px 36px rgba(139,110,60,.16), 0 1px 0 rgba(255,255,255,.90) inset;
}
.bk-kpi.is-kpi-active {
  border-color: var(--kpi-accent) !important;
  outline: 2.5px solid var(--kpi-accent);
  outline-offset: 2px;
  transform: translateY(-4px);
  background: rgba(255,255,255,.65);
  box-shadow: 0 12px 36px rgba(139,110,60,.18), 0 1px 0 rgba(255,255,255,.90) inset !important;
}
.bk-kpi-icon {
  font-size: 1.3rem; margin-bottom: .55rem; line-height: 1;
  display: block;
}
.bk-kpi-val {
  font-family: var(--font-display);
  font-size: 1.9rem; font-weight: 700;
  letter-spacing: -.02em; line-height: 1;
  color: var(--kpi-accent, var(--gold-dim));
  margin-bottom: .3rem;
}
.bk-kpi-label {
  font-family: var(--font-mono);
  font-size: .58rem; font-weight: 500;
  letter-spacing: .1em; text-transform: uppercase;
  color: var(--text-dim);
}
/* Dark glassmorphism KPI */
[data-theme="dark"] .bk-kpi {
  background: rgba(18,24,38,.50) !important;
  backdrop-filter: blur(20px) saturate(1.5) !important;
  -webkit-backdrop-filter: blur(20px) saturate(1.5) !important;
  border: 1.5px solid rgba(255,255,255,.07) !important;
  border-top: 1.5px solid rgba(255,255,255,.13) !important;
  border-left: 1.5px solid rgba(255,255,255,.09) !important;
  box-shadow: 0 4px 24px rgba(0,0,0,.35), 0 1px 0 rgba(255,255,255,.04) inset !important;
}
[data-theme="dark"] .bk-kpi:hover {
  background: rgba(24,32,50,.68) !important;
  border-color: rgba(255,255,255,.16) !important;
  box-shadow: 0 12px 36px rgba(0,0,0,.50), 0 1px 0 rgba(255,255,255,.06) inset !important;
}
[data-theme="dark"] .bk-kpi.is-kpi-active {
  background: rgba(24,32,50,.72) !important;
  border-color: var(--kpi-accent) !important;
  box-shadow: 0 12px 36px rgba(0,0,0,.50) !important;
}

/* ── Main panel ── */
.bk-panel {
  background: var(--card-bg);
  border: 1.5px solid var(--gold-border);
  border-radius: var(--r-xl);
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(139,110,60,.09);
}
[data-theme="dark"] .bk-panel {
  background: rgba(18,24,38,.85);
  border-color: rgba(201,168,76,.18);
  box-shadow: 0 4px 24px rgba(0,0,0,.30);
}

/* Panel header */
.bk-panel-head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.2rem 1.6rem;
  background: linear-gradient(135deg, #FBF6EC 0%, #F5EDDA 100%);
  border-bottom: 1.5px solid var(--gold-border);
}
[data-theme="dark"] .bk-panel-head {
  background: linear-gradient(135deg, rgba(28,22,10,.95), rgba(18,14,6,.98));
  border-bottom-color: rgba(201,168,76,.22);
}
.bk-panel-title {
  font-family: var(--font-display);
  font-size: 1rem; font-weight: 600; font-style: italic;
  color: var(--text-primary);
}
.bk-panel-count {
  font-family: var(--font-mono); font-size: .62rem;
  color: var(--text-dim); letter-spacing: .06em;
  background: var(--gold-lt); border: 1px solid var(--gold-border);
  padding: .18rem .6rem; border-radius: 99px;
}

/* Filter bar */
.bk-filter-bar {
  display: flex; align-items: center; gap: .5rem;
  padding: 1rem 1.6rem;
  border-bottom: 1px solid var(--gold-border);
  flex-wrap: wrap;
  background: rgba(201,168,76,.03);
}
[data-theme="dark"] .bk-filter-bar {
  background: rgba(255,255,255,.02);
  border-bottom-color: rgba(201,168,76,.12);
}
.bk-filter-btn {
  font-family: var(--font-mono); font-size: .65rem; font-weight: 500;
  letter-spacing: .07em; text-transform: uppercase;
  padding: .35rem .9rem; border-radius: 99px;
  border: 1px solid var(--gold-border);
  color: var(--text-muted); background: transparent;
  transition: all .15s; cursor: pointer; white-space: nowrap;
}
.bk-filter-btn:hover { background: var(--gold-soft); color: var(--text-primary); border-color: var(--gold-border-md); }
.bk-filter-btn.active { background: var(--gold-lt); color: var(--gold-dim); border-color: var(--gold-border-md); font-weight: 600; }

/* Coloured active states per status */
.bk-filter-btn.active[data-filter="pending"]     { background:rgba(217,119,6,.12);  color:#D97706; border-color:rgba(217,119,6,.40); }
.bk-filter-btn.active[data-filter="confirmed"]   { background:rgba(22,163,74,.12);  color:#16A34A; border-color:rgba(22,163,74,.40); }
.bk-filter-btn.active[data-filter="completed"]   { background:rgba(37,99,235,.12);  color:#2563EB; border-color:rgba(37,99,235,.40); }
.bk-filter-btn.active[data-filter="cancelled"]   { background:rgba(220,38,38,.12);  color:#DC2626; border-color:rgba(220,38,38,.40); }
.bk-filter-btn.active[data-filter="in_progress"] { background:rgba(234,88,12,.12);  color:#EA580C; border-color:rgba(234,88,12,.40); }
.bk-filter-btn.active[data-filter="rescheduled"] { background:rgba(124,58,237,.12); color:#7C3AED; border-color:rgba(124,58,237,.40); }

.bk-search {
  font-family: var(--font-mono); font-size: .72rem;
  padding: .38rem 1rem; border-radius: 99px;
  border: 1px solid var(--gold-border);
  background: rgba(255,255,255,.70); color: var(--text-primary);
  width: 240px; outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.bk-search:focus { border-color: var(--gold-border-md); box-shadow: 0 0 0 3px rgba(201,168,76,.10); }
.bk-search::placeholder { color: var(--text-dim); }

/* Table */
.bk-table-wrap { overflow-x: auto; }
.bk-table {
  width: 100%; border-collapse: collapse;
  font-size: .84rem;
}
.bk-table thead {
  background: linear-gradient(135deg, #FAF8F3, #F5EFE0);
}
[data-theme="dark"] .bk-table thead {
  background: rgba(255,255,255,.03);
}
.bk-table th {
  font-family: var(--font-mono); font-size: .6rem; font-weight: 500;
  letter-spacing: .1em; text-transform: uppercase;
  color: var(--gold-dim); padding: .9rem 1.4rem;
  text-align: left; border-bottom: 1.5px solid var(--gold-border);
  white-space: nowrap;
}
.bk-table td {
  padding: 1rem 1.4rem;
  border-bottom: 1px solid rgba(201,168,76,.10);
  color: var(--text-muted);
  vertical-align: middle;
}
[data-theme="dark"] .bk-table td { border-bottom-color: rgba(255,255,255,.05); }
.bk-table tr:last-child td { border-bottom: none; }
.bk-table tbody tr { transition: background .12s; }
.bk-table tbody tr:hover { background: rgba(201,168,76,.05); }
[data-theme="dark"] .bk-table tbody tr:hover { background: rgba(255,255,255,.03); }

/* Cell types */
.bk-td-id {
  font-family: var(--font-mono); font-size: .72rem;
  color: var(--text-dim); font-weight: 500;
}
.bk-td-customer {
  display: flex; align-items: center; gap: .7rem;
}
.bk-td-av {
  width: 32px; height: 32px; border-radius: 99px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--gold-dim), var(--gold));
  color: #1a1200; font-family: var(--font-display);
  font-weight: 700; font-size: .62rem;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 0 0 2px rgba(201,168,76,.22);
}
.bk-td-name { font-weight: 600; color: var(--text-primary); font-size: .84rem; }
.bk-td-service { font-weight: 500; color: var(--text-primary); }
.bk-td-provider { color: var(--text-muted); font-size: .8rem; }
.bk-td-date { font-family: var(--font-mono); font-size: .72rem; color: var(--text-dim); white-space: nowrap; }
.bk-td-amount { font-family: var(--font-mono); color: var(--gold-dim); font-weight: 600; font-size: .84rem; }

/* Status pills */
.adm-pill {
  display: inline-flex; align-items: center; gap: .35rem;
  font-family: var(--font-mono);
  font-size: .6rem; font-weight: 600;
  letter-spacing: .06em; text-transform: uppercase;
  padding: .3rem .85rem; border-radius: 99px;
  border: 1px solid transparent; white-space: nowrap;
}
.adm-pill::before {
  content: ''; width: 5px; height: 5px;
  border-radius: 99px; background: currentColor; flex-shrink: 0;
}
.adm-pill--pending     { background:rgba(217,119,6,.10);  color:#D97706; border-color:rgba(217,119,6,.30); }
.adm-pill--confirmed   { background:rgba(22,163,74,.10);  color:#16A34A; border-color:rgba(22,163,74,.30); }
.adm-pill--completed   { background:rgba(37,99,235,.10);  color:#2563EB; border-color:rgba(37,99,235,.30); }
.adm-pill--cancelled   { background:rgba(220,38,38,.10);  color:#DC2626; border-color:rgba(220,38,38,.30); }
.adm-pill--in_progress { background:rgba(234,88,12,.10);  color:#EA580C; border-color:rgba(234,88,12,.30); }
.adm-pill--rescheduled { background:rgba(124,58,237,.10); color:#7C3AED; border-color:rgba(124,58,237,.30); }

/* Empty */
.bk-empty {
  padding: 4rem 2rem; text-align: center;
  color: var(--text-dim); font-size: .85rem;
}
.bk-empty-icon { font-size: 2.5rem; margin-bottom: .8rem; opacity: .35; }

/* Animations */
@keyframes fadeUp {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:none; }
}
.anim-1 { animation: fadeUp .4s var(--ease-out) both; }
.anim-2 { animation: fadeUp .4s var(--ease-out) .08s both; }
.anim-3 { animation: fadeUp .4s var(--ease-out) .16s both; }

.bk-kpi-icon { display: none; }

/* Responsive */
@media(max-width:1200px){ .bk-kpi-grid{ grid-template-columns:repeat(4,1fr); } }
@media(max-width:800px) { .bk-kpi-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:700px) { .bk-search{ width:100%; } }
</style>
</head>
<body>
<div class="grain"></div>

<?php require_once __DIR__ . '/_nav.php'; adminNav('bookings'); ?>

<div class="bk-page">

  <!-- Header -->
  <div class="bk-header anim-1">
    <div class="bk-eyebrow"><span class="bk-eyebrow-dot"></span>Management</div>
    <h1 class="bk-title">All <em>Bookings</em></h1>
    <p class="bk-subtitle">Platform-wide booking history and status control</p>
  </div>

  <!-- KPI Grid -->
  <div class="bk-kpi-grid anim-2">
    <?php foreach ($kpiConfig as $key => $kpi):
      $accent = $kpi['color'];
      $rgb    = sscanf(ltrim($accent,'#'),'%02x%02x%02x');
      $glow   = 'rgba(' . implode(',', $rgb) . ',.12)';
      $isRev  = $key === 'revenue';
      $isTotal = $key === 'total';
      $filter = ($isRev || $isTotal) ? 'all' : $key;
    ?>
    <div class="bk-kpi <?= $key === 'total' ? 'is-kpi-active' : '' ?>"
         style="--kpi-accent:<?= $accent ?>;--kpi-glow:<?= $glow ?>"
         data-kpi-filter="<?= $filter ?>"
         role="button" tabindex="0"
         title="Filter by <?= $kpi['label'] ?>">
      <div class="bk-kpi-val"><?= $isRev ? '₱'.number_format((float)$kpi['val'],0) : number_format((int)$kpi['val']) ?></div>
      <div class="bk-kpi-label"><?= $kpi['label'] ?></div>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Bookings Panel -->
  <div class="bk-panel anim-3">

    <div class="bk-panel-head">
      <span class="bk-panel-title">Booking Records</span>
      <input class="bk-search" type="search" id="bk-search" placeholder="Search customer, service…">
    </div>

    <div class="bk-table-wrap">
      <table class="bk-table" id="bookings-table">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Customer</th>
            <th>Service</th>
            <th>Provider</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($bookings)): ?>
          <tr>
            <td colspan="7">
              <div class="bk-empty">
                <div class="bk-empty-icon">📭</div>
                <p>No bookings found.</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($bookings as $b):
            $initials = strtoupper(substr($b['cust_first'],0,1).substr($b['cust_last'],0,1));
            $sc       = in_array($b['status'], $statusOptions) ? $b['status'] : 'default';
            $label    = ucfirst(str_replace('_', ' ', $b['status']));
            $search   = strtolower($b['cust_first'].' '.$b['cust_last'].' '.$b['service_name'].' '.$b['prov_first'].' '.$b['prov_last']);
          ?>
          <tr data-status="<?= htmlspecialchars($b['status']) ?>"
              data-search="<?= htmlspecialchars($search) ?>">
            <td class="bk-td-id">#<?= $b['id'] ?></td>
            <td>
              <div class="bk-td-customer">
                <div class="bk-td-av"><?= $initials ?></div>
                <span class="bk-td-name"><?= htmlspecialchars($b['cust_first'].' '.$b['cust_last']) ?></span>
              </div>
            </td>
            <td class="bk-td-service"><?= htmlspecialchars($b['service_name']) ?></td>
            <td class="bk-td-provider"><?= htmlspecialchars($b['prov_first'].' '.$b['prov_last']) ?></td>
            <td class="bk-td-date"><?= date('M d, Y', strtotime($b['booking_date'])) ?></td>
            <td class="bk-td-amount">₱<?= number_format($b['total_amount'], 2) ?></td>
            <td><span class="adm-pill adm-pill--<?= $sc ?>"><?= $label ?></span></td>
          </tr>
          <?php endforeach ?>
        <?php endif ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /bk-page -->

<script>
let activeFilter = 'all';

// ── KPI card click → filter table ──
document.querySelectorAll('.bk-kpi[data-kpi-filter]').forEach(card => {
  card.addEventListener('click', () => {
    activeFilter = card.dataset.kpiFilter;
    document.querySelectorAll('.bk-kpi').forEach(c => c.classList.remove('is-kpi-active'));
    card.classList.add('is-kpi-active');
    applyFilters();
  });
});

document.getElementById('bk-search').addEventListener('input', applyFilters);

function applyFilters() {
  const q = document.getElementById('bk-search').value.toLowerCase().trim();
  document.querySelectorAll('#bookings-table tbody tr[data-status]').forEach(row => {
    const matchStatus = activeFilter === 'all' || row.dataset.status === activeFilter;
    const matchSearch = !q || row.dataset.search.includes(q);
    row.style.display = matchStatus && matchSearch ? '' : 'none';
  });
}

// Run on page load to show all rows
applyFilters();
</script>
</body>
</html>