<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PBX Popup</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;padding:30px;color:#333}
.box{max-width:600px;margin:0 auto;background:#fff;border-radius:6px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.1)}
h2{margin-top:0}
.phone{color:#777;font-size:14px;margin-bottom:20px}
ul{list-style:none;padding:0;margin:0}
li{padding:10px 0;border-bottom:1px solid #eee}
li:last-child{border-bottom:none}
a{color:#2c99f0;text-decoration:none;font-weight:bold}
.type{color:#999;font-size:12px;text-transform:uppercase;margin-left:8px}
.empty{color:#999;padding:20px 0}
.newlead{display:inline-block;margin-top:20px;padding:8px 16px;background:#2c99f0;color:#fff;border-radius:4px;text-decoration:none}
</style>
</head>
<body>
<div class="box">
<h2>Incoming Call</h2>
<div class="phone">নম্বর: <?= htmlspecialchars($phone) ?></div>
<ul>
<?php
$found = false;
foreach ($results as $block) {
    if (!in_array($block['type'], ['clients', 'leads'])) continue;
    foreach ($block['result'] as $row) {
        $found = true;
        if ($block['type'] === 'leads') {
            $name = $row['name'] ?? '(no name)';
            $link = admin_url('leads/index/' . $row['id']);
        } else {
            $name = $row['company'] ?? ('Client #' . $row['userid']);
            $link = admin_url('clients/client/' . $row['userid']);
        }
        echo '<li><a href="' . $link . '">' . htmlspecialchars($name) . '</a><span class="type">' . htmlspecialchars($block['search_heading']) . '</span></li>';
    }
}
?>
</ul>
<?php if (!$found): ?>
<div class="empty">এই নম্বরে কোনো Lead বা Client পাওয়া যায়নি।</div>
<?php endif; ?>
<a class="newlead" href="<?= admin_url('leads') ?>">Leads লিস্ট দেখুন</a>
</div>
</body>
</html>
