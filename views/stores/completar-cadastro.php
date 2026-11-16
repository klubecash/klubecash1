<?php
// views/stores/completar-cadastro.php
// Cadastro do lojista em etapas, logo depois de assinar o KlubeCash na Loja do Grupo Klube. O painel só abre quando termina.
declare(strict_types=1);

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../services/StoreProvision/StoreOnboardingService.php';

use App\Services\StoreProvision\StoreOnboardingService;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Cache-Control: no-store');

$storeId = (int) ($_SESSION['store_id'] ?? 0);
$userType = (string) ($_SESSION['user_type'] ?? '');
if ($storeId <= 0 || !in_array($userType, ['loja', 'funcionario'], true)) {
    header('Location: /login', true, 302);
    exit;
}
$e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$db = Database::getConnection();
$service = new StoreOnboardingService($db);
if (!$service->pending($storeId)) {
    $_SESSION['store_onboarding_ok'] = $storeId;
    header('Location: /store', true, 302);
    exit;
}
if ($userType !== 'loja') {
    // Funcionário: só o dono completa o cadastro.
    http_response_code(200);
    echo '<!DOCTYPE html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Cadastro da loja · KlubeCash</title><body style="font-family:system-ui;max-width:520px;margin:12vh auto;padding:0 20px;text-align:center"><h1>Quase lá</h1><p>O dono da loja ainda precisa concluir o cadastro. Assim que terminar, o painel abre para você.</p></body></html>';
    exit;
}

if (empty($_SESSION['onboarding_csrf'])) {
    $_SESSION['onboarding_csrf'] = bin2hex(random_bytes(16));
}
$errors = [];
$data = $service->load($storeId);
$step = $data['step'];
if (isset($_GET['etapa'])) {
    $step = max(1, min($data['step'], (int) $_GET['etapa'])); // pode voltar às etapas já feitas, nunca pular
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $given = (string) ($_POST['csrf'] ?? '');
    if (!hash_equals((string) $_SESSION['onboarding_csrf'], $given)) {
        $errors = ['A página expirou. Tente de novo.'];
    } else {
        $posted = (int) ($_POST['etapa'] ?? $step);
        $result = $service->save($storeId, min($posted, $data['step']), $_POST);
        if ($result['ok'] && $posted >= StoreOnboardingService::STEPS) {
            $_SESSION['store_onboarding_ok'] = $storeId;
            header('Location: /store?cadastro=concluido', true, 303);
            exit;
        }
        if ($result['ok']) {
            header('Location: /store/completar-cadastro?etapa=' . $result['step'], true, 303);
            exit;
        }
        $errors = $result['errors'];
        $step = $posted;
        $data['store'] = array_replace($data['store'], $_POST);
        $data['address'] = array_replace($data['address'], $_POST);
    }
}
$s = $data['store'];
$a = $data['address'];
$labels = ['Seu negócio', 'Endereço', 'Cashback', 'Revisar'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Complete o cadastro da sua loja · KlubeCash</title>
<style>
:root{--o:#FC6914;--ol:#FF8F4F;--bg:#FFF9F5;--ink:#1A0800;--mut:#6B5C52;--card:#fff;--line:#EBDCD0}
*{box-sizing:border-box}body{margin:0;font-family:Nunito,system-ui,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}
.wrap{max-width:640px;margin:0 auto;padding:28px 18px 60px}
.brand{font-weight:800;font-size:22px;color:var(--o);margin-bottom:18px}
h1{font-size:28px;margin:6px 0 4px}p.sub{color:var(--mut);margin:0 0 22px}
.steps{display:flex;gap:8px;margin:0 0 22px;padding:0;list-style:none}
.steps li{flex:1;text-align:center;font-size:13px;color:var(--mut);padding-top:10px;border-top:5px solid var(--line)}
.steps li.on{color:var(--ink);font-weight:700;border-color:var(--o)}.steps li.done{border-color:var(--ol);color:var(--ink)}
.steps a{color:inherit;text-decoration:none}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:22px}
label{display:block;font-weight:700;font-size:14px;margin:14px 0 6px}label:first-child{margin-top:0}
input,select,textarea{width:100%;padding:12px 14px;border:1.5px solid var(--line);border-radius:12px;font:inherit;background:#fff}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--o)}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}@media(max-width:520px){.row{grid-template-columns:1fr}}
.erros{background:#FDECEA;border:1px solid #F3B7B0;color:#8A1C12;border-radius:12px;padding:12px 16px;margin-bottom:16px}.erros ul{margin:0;padding-left:18px}
.acoes{display:flex;gap:10px;justify-content:space-between;margin-top:22px}
.btn{border:0;border-radius:999px;padding:13px 26px;font:inherit;font-weight:800;cursor:pointer;background:var(--o);color:#fff}
.btn.sec{background:transparent;color:var(--mut);border:1.5px solid var(--line);text-decoration:none;display:inline-block}
dl{margin:0;display:grid;grid-template-columns:140px 1fr;gap:8px 12px}dt{color:var(--mut)}dd{margin:0;font-weight:700}
.check{display:flex;gap:12px;align-items:flex-start;padding:14px;border:1.5px solid var(--line);border-radius:14px;margin-top:10px;cursor:pointer}
.check input{width:auto;margin-top:3px}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">KlubeCash</div>
  <h1>Vamos deixar a sua loja pronta</h1>
  <p class="sub">A sua assinatura já está ativa e a loja já foi aprovada. Falta só completar o cadastro, em <?= (int) StoreOnboardingService::STEPS ?> etapas rápidas. Você pode sair e voltar de onde parou.</p>
  <ol class="steps">
    <?php foreach ($labels as $i => $label): $n = $i + 1; ?>
      <li class="<?= $n === $step ? 'on' : ($n < $data['step'] ? 'done' : '') ?>"><?php if ($n <= $data['step']): ?><a href="/store/completar-cadastro?etapa=<?= $n ?>"><?= $n ?>. <?= $e($label) ?></a><?php else: ?><?= $n ?>. <?= $e($label) ?><?php endif; ?></li>
    <?php endforeach; ?>
  </ol>

  <form class="card" method="post" action="/store/completar-cadastro" autocomplete="off">
    <?php if ($errors): ?><div class="erros" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= $e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= $e($_SESSION['onboarding_csrf']) ?>">
    <input type="hidden" name="etapa" value="<?= (int) $step ?>">

    <?php if ($step === 1): ?>
      <label for="nome_fantasia">Nome da loja (como o cliente conhece)</label>
      <input id="nome_fantasia" name="nome_fantasia" maxlength="100" required value="<?= $e($s['nome_fantasia'] ?? '') ?>">
      <label for="razao_social">Razão social</label>
      <input id="razao_social" name="razao_social" maxlength="150" required value="<?= $e($s['razao_social'] ?? '') ?>">
      <div class="row">
        <div><label for="telefone">Telefone com DDD</label><input id="telefone" name="telefone" inputmode="tel" required value="<?= $e($s['telefone'] ?? '') ?>"></div>
        <div><label for="categoria">Categoria</label>
          <select id="categoria" name="categoria" required>
            <option value="">Escolha…</option>
            <?php foreach (StoreOnboardingService::CATEGORIES as $c): ?><option value="<?= $e($c) ?>"<?= (($s['categoria'] ?? '') === $c && $c !== 'Outros') || (($s['categoria'] ?? '') === 'Outros' && $c === 'Outros' && isset($_POST['categoria'])) ? ' selected' : '' ?>><?= $e($c) ?></option><?php endforeach; ?>
          </select></div>
      </div>
      <label for="descricao">Conte em poucas palavras o que a sua loja vende (opcional)</label>
      <textarea id="descricao" name="descricao" rows="3" maxlength="500"><?= $e($s['descricao'] ?? '') ?></textarea>

    <?php elseif ($step === 2): ?>
      <div class="row">
        <div><label for="cep">CEP</label><input id="cep" name="cep" inputmode="numeric" maxlength="9" required value="<?= $e($a['cep'] ?? '') ?>"></div>
        <div><label for="estado">Estado</label>
          <select id="estado" name="estado" required><option value="">Escolha…</option>
            <?php foreach (StoreOnboardingService::STATES as $uf): ?><option<?= ($a['estado'] ?? '') === $uf ? ' selected' : '' ?>><?= $e($uf) ?></option><?php endforeach; ?></select></div>
      </div>
      <label for="logradouro">Rua</label><input id="logradouro" name="logradouro" maxlength="255" required value="<?= $e($a['logradouro'] ?? '') ?>">
      <div class="row">
        <div><label for="numero">Número</label><input id="numero" name="numero" maxlength="20" required value="<?= $e($a['numero'] ?? '') ?>"></div>
        <div><label for="complemento">Complemento (opcional)</label><input id="complemento" name="complemento" maxlength="100" value="<?= $e($a['complemento'] ?? '') ?>"></div>
      </div>
      <div class="row">
        <div><label for="bairro">Bairro</label><input id="bairro" name="bairro" maxlength="100" required value="<?= $e($a['bairro'] ?? '') ?>"></div>
        <div><label for="cidade">Cidade</label><input id="cidade" name="cidade" maxlength="100" required value="<?= $e($a['cidade'] ?? '') ?>"></div>
      </div>

    <?php elseif ($step === 3): ?>
      <p style="margin-top:0">O cliente recebe <b>5% de cashback</b> em cada compra na sua loja e volta para gastar com você. Você pode mudar isso depois, no painel.</p>
      <label class="check"><input type="radio" name="cashback_ativo" value="1"<?= ((int) ($s['cashback_ativo'] ?? 1)) === 1 ? ' checked' : '' ?>><span><b>Ativar o cashback agora</b><br><span style="color:var(--mut)">Recomendado: as vendas já entram valendo.</span></span></label>
      <label class="check"><input type="radio" name="cashback_ativo" value="0"<?= ((int) ($s['cashback_ativo'] ?? 1)) === 0 && isset($_POST['cashback_ativo']) ? ' checked' : '' ?>><span><b>Deixar para depois</b><br><span style="color:var(--mut)">A loja abre sem cashback até você ativar.</span></span></label>

    <?php else: ?>
      <h2 style="margin-top:0">Confira e conclua</h2>
      <dl>
        <dt>Loja</dt><dd><?= $e($s['nome_fantasia'] ?? '') ?></dd>
        <dt>Razão social</dt><dd><?= $e($s['razao_social'] ?? '') ?></dd>
        <dt>Telefone</dt><dd><?= $e($s['telefone'] ?? '') ?></dd>
        <dt>Categoria</dt><dd><?= $e($s['categoria'] ?? '') ?></dd>
        <dt>Endereço</dt><dd><?= $e(trim(($a['logradouro'] ?? '') . ', ' . ($a['numero'] ?? '') . ' · ' . ($a['bairro'] ?? '') . ' · ' . ($a['cidade'] ?? '') . '/' . ($a['estado'] ?? ''), ' ,·/')) ?></dd>
        <dt>Cashback</dt><dd><?= ((int) ($s['cashback_ativo'] ?? 0)) === 1 ? 'Ativo (5% para o cliente)' : 'Desativado por enquanto' ?></dd>
      </dl>
    <?php endif; ?>

    <div class="acoes">
      <?php if ($step > 1): ?><a class="btn sec" href="/store/completar-cadastro?etapa=<?= (int) $step - 1 ?>">Voltar</a><?php else: ?><span></span><?php endif; ?>
      <button class="btn" type="submit"><?= $step >= StoreOnboardingService::STEPS ? 'Concluir e abrir o painel' : 'Continuar' ?></button>
    </div>
  </form>
</div>
</body>
</html>
