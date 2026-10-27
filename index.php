<?php
require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/public_parts.php';
$feedback = $_SESSION['contact_feedback'] ?? [];
unset($_SESSION['contact_feedback']);
$values = $feedback['values'] ?? [];
$errors = $feedback['errors'] ?? [];
$appUrl = rtrim((string)($config['site']['app_url'] ?? ''), '/') ?: 'https://one.qeai.com.br';
function field_attributes(string $name, array $errors): string {
    return isset($errors[$name]) ? ' aria-invalid="true" aria-describedby="' . e($name) . '-error"' : '';
}
function field_error(string $name, array $errors): void {
    if (isset($errors[$name])) echo '<small class="field-error" id="' . e($name) . '-error">' . e($errors[$name]) . '</small>';
}
public_header($config, 'QEAI — Marketing com inteligência, pessoas e automação');
?>
<main id="conteudo">
<header class="hero">
  <div class="hero-bg" aria-hidden="true"><span class="hero-orb orb-one"></span><span class="hero-orb orb-two"></span><span class="hero-orb orb-three"></span></div>
  <div class="container hero-grid">
    <div>
      <span class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> Estratégia humana. Potência artificial.</span>
      <h1>Mais presença.<br> Mais identidade.<br> <span class="gradient-text">Mais possibilidades.</span></h1>
      <p>Conectamos estratégia, conteúdo e inteligência artificial para colocar sua marca em movimento. Com criatividade, consistência e pessoas no comando.</p>
      <div class="hero-actions"><a class="btn btn-primary" href="#contato">Vamos falar da sua marca <span aria-hidden="true">↗</span></a><a class="btn btn-hero-ghost" href="<?=e($appUrl)?>" target="_blank" rel="noopener noreferrer">Acessar QEAI One <span aria-hidden="true">↗</span></a><a class="text-link" href="#servicos">Conheça nossas soluções <span aria-hidden="true">↓</span></a></div>
      <div class="trust"><span>Criação com identidade</span><span>Automação com propósito</span></div>
    </div>
    <div class="hero-art">
      <div class="art-label"><span class="live-dot" aria-hidden="true"></span> Pessoas + ideias + tecnologia</div>
      <img src="assets/img/team.svg" alt="Três pessoas colaborando na criação de uma marca" width="760" height="620" fetchpriority="high">
      <div class="art-caption"><span class="art-spark" aria-hidden="true">✦</span><div><strong>A inteligência está na conexão.</strong><span>Sua essência, ampliada pela tecnologia.</span></div></div>
    </div>
  </div>
</header>
<div class="expertise-strip"><div class="container"><span>Da ideia à execução</span><p>Estratégia <i>✦</i> Conteúdo <i>✦</i> Design <i>✦</i> Automação</p></div></div>
<section class="section" id="servicos">
  <div class="container">
    <div class="section-head"><span class="eyebrow">01 / O que fazemos</span><h2>Uma marca forte precisa<br> de uma operação conectada.</h2><p>Da primeira pauta à próxima campanha, combinamos visão estratégica e criação para construir uma presença que faz sentido para o seu negócio.</p></div>
    <div class="grid-3">
      <article class="card"><div class="card-top"><div class="icon" aria-hidden="true">✦</div><span>01</span></div><h3>Conteúdo & Social</h3><p>Sua marca na conversa certa, com uma voz que as pessoas reconhecem.</p><ul><li>Planejamento editorial</li><li>Textos e conteúdo para redes</li><li>Consistência de tom e presença</li></ul></article>
      <article class="card"><div class="card-top"><div class="icon" aria-hidden="true">◈</div><span>02</span></div><h3>Imagem & Vídeo</h3><p>Ideias que ganham forma com direção de arte e criação apoiada por IA.</p><ul><li>Peças para diferentes canais</li><li>Identidade visual nas campanhas</li><li>Adaptação de formatos</li></ul></article>
      <article class="card"><div class="card-top"><div class="icon" aria-hidden="true">↗</div><span>03</span></div><h3>Automação inteligente</h3><p>Mais espaço para pensar, criar e decidir. Menos tempo em tarefas repetitivas.</p><ul><li>Organização de fluxos</li><li>Ferramentas conectadas</li><li>Supervisão e aprovação humana</li></ul></article>
    </div>
  </div>
</section>
<section class="section soft" id="como-funciona">
  <div class="container"><div class="section-head"><span class="eyebrow">02 / Nosso processo</span><h2>Tecnologia nos bastidores.<br> Sua marca em primeiro plano.</h2><p>Um caminho claro, com contexto, colaboração e espaço para evoluir.</p></div>
    <div class="process">
      <article class="step"><h3>Escutar</h3><p>Entendemos sua marca, seu público e o que você quer construir.</p></article>
      <article class="step"><h3>Planejar</h3><p>Transformamos objetivos em pautas, prioridades e uma direção criativa.</p></article>
      <article class="step"><h3>Criar</h3><p>Unimos pessoas e ferramentas para produzir conteúdo com contexto.</p></article>
      <article class="step"><h3>Evoluir</h3><p>Com aprovação e análise, ajustamos o caminho e melhoramos a próxima entrega.</p></article>
    </div>
  </div>
</section>
<section class="section"><div class="container split"><div><span class="eyebrow">03 / Nossa essência</span><h2>Automatizar tarefas.<br> Preservar o que<br> torna você único.</h2><p class="muted">Acreditamos que a tecnologia deve ampliar a criatividade. Por isso, estratégia, intenção e bom senso orientam cada etapa do nosso trabalho.</p><a class="text-link" href="#contato">Vamos construir essa conexão <span aria-hidden="true">↗</span></a></div><div class="quote-card"><span class="quote-mark" aria-hidden="true">✳</span><strong>Pessoas + ideias + IA.<br> O resultado tem<br> a sua identidade.</strong><p>Ferramentas ajudam a produzir e organizar.<br> Pessoas dão significado e direção.</p><div class="pills"><span class="pill">Estratégia</span><span class="pill">Criatividade</span><span class="pill">Tecnologia</span></div></div></div></section>
<section class="section product-section" id="qeai-one">
  <div class="container">
    <div class="product-card">
      <div class="product-copy">
        <span class="eyebrow">04 / Produto QEAI</span>
        <h2>QEAI One.<br> Seu CRM, do seu jeito.</h2>
        <p>Centralize leads, personalize os campos do seu processo e acompanhe tudo em tabela, kanban, calendário ou cronograma. Uma operação simples, segura e feita para crescer com o seu negócio.</p>
        <ul class="product-list">
          <li>Campos personalizados com histórico completo</li>
          <li>Visões de tabela, kanban, calendário e cronograma</li>
          <li>Permissões por equipe e auditoria de ações</li>
        </ul>
        <div class="product-actions">
          <a class="btn btn-primary" href="<?=e($appUrl)?>" target="_blank" rel="noopener noreferrer">Acessar QEAI One <span aria-hidden="true">↗</span></a>
          <a class="text-link" href="#contato">Falar com especialista <span aria-hidden="true">→</span></a>
        </div>
      </div>
      <div class="product-visual" aria-hidden="true">
        <div class="product-window">
          <div class="product-window-bar"><i></i><i></i><i></i><span>QEAI One · CRM</span></div>
          <div class="product-kpis">
            <div><small>Leads</small><strong>1.248</strong></div>
            <div><small>Oportunidades</small><strong>290</strong></div>
            <div><small>Clientes</small><strong>128</strong></div>
          </div>
          <div class="product-bars"><i style="height:34%"></i><i style="height:52%"></i><i style="height:44%"></i><i style="height:68%"></i><i style="height:58%"></i><i style="height:82%"></i><i style="height:72%"></i><i style="height:94%"></i></div>
          <div class="product-tags"><span>Novo lead recebido</span><span>Prioridade alta</span><span>Próximo contato hoje</span></div>
        </div>
      </div>
    </div>
  </div>
</section>
<section class="section contact-section" id="contato">
  <div class="container contact-wrap">
    <div class="contact-intro"><span class="eyebrow">05 / Vamos conversar</span><h2>O próximo passo<br> da sua marca<br> começa aqui.</h2><p>Conte um pouco sobre seu negócio e o que você tem em mente. Vamos entender como podemos ajudar.</p><a class="contact-email" href="mailto:<?=e($config['site']['contact_email'])?>"><span class="icon" aria-hidden="true">↗</span><span><small>Prefere escrever diretamente?</small><strong><?=e($config['site']['contact_email'])?></strong></span></a><div class="contact-note"><span aria-hidden="true">✦</span><p>Uma boa conversa é o começo<br> de uma boa estratégia.</p></div></div>
    <div class="contact-form-card">
      <h3>Fale sobre seu projeto</h3><p class="form-intro">Os campos com asterisco (*) são obrigatórios.</p>
      <?php if ($feedback): ?><div id="form-feedback" tabindex="-1" role="<?=($feedback['type'] ?? '') === 'ok' ? 'status' : 'alert'?>" class="status <?=($feedback['type'] ?? '') === 'ok' ? 'ok' : 'err'?>"><?=e($feedback['message'] ?? '')?></div><?php endif; ?>
      <?php if (!empty($config['features']['contact_form'])): ?>
      <form action="contato" method="post" class="form-grid" data-contact-form>
        <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
        <div class="hp" aria-hidden="true"><label>Deixe este campo vazio<input name="website" autocomplete="off" tabindex="-1"></label></div>
        <div class="field"><label for="name">Seu nome <span>*</span></label><input id="name" name="name" required maxlength="100" autocomplete="name" placeholder="Como podemos chamar você?" value="<?=e($values['name'] ?? '')?>"<?=field_attributes('name', $errors)?>><?php field_error('name', $errors); ?></div>
        <div class="field"><label for="email">Seu e-mail <span>*</span></label><input id="email" name="email" type="email" required maxlength="180" autocomplete="email" placeholder="voce@empresa.com.br" value="<?=e($values['email'] ?? '')?>"<?=field_attributes('email', $errors)?>><?php field_error('email', $errors); ?></div>
        <div class="field full"><label for="company">Empresa ou marca <small>(opcional)</small></label><input id="company" name="company" maxlength="140" autocomplete="organization" placeholder="Qual é o nome do seu negócio?" value="<?=e($values['company'] ?? '')?>"<?=field_attributes('company', $errors)?>><?php field_error('company', $errors); ?></div>
        <div class="field full"><label for="message">Como podemos ajudar? <span>*</span></label><textarea id="message" name="message" required maxlength="4000" rows="5" placeholder="Conte sobre sua marca, seus objetivos ou aquela ideia que quer tirar do papel…"<?=field_attributes('message', $errors)?>><?=e($values['message'] ?? '')?></textarea><div class="field-meta"><span>Conte do seu jeito.</span><span data-message-count>0 / 4.000</span></div><?php field_error('message', $errors); ?></div>
        <div class="field full consent-field"><label class="check" for="privacy"><input id="privacy" type="checkbox" name="privacy" value="1" required<?=field_attributes('privacy', $errors)?>><span>Li a <a href="privacidade" target="_blank" rel="noopener">Política de Privacidade</a> e autorizo o uso dos meus dados para responder a este contato.</span></label><?php field_error('privacy', $errors); ?></div>
        <div class="field full"><button class="btn btn-primary submit-button" type="submit"><span data-submit-label>Enviar mensagem</span><span aria-hidden="true">↗</span></button><small class="form-reassurance">Seus dados serão usados para responder à sua solicitação.</small></div>
      </form>
      <?php else: ?><div class="status">No momento, fale com a gente pelo <a href="mailto:<?=e($config['site']['contact_email'])?>">e-mail ao lado</a>.</div><?php endif; ?>
    </div>
  </div>
</section>
</main>
<?php public_footer($config); ?>


