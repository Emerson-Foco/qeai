<?php
require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/public_parts.php';
public_header($config, 'Termos de Uso — Q One', ['canonical' => rtrim((string)$config['site']['url'], '/') . '/termos']);
?>
<main id="conteudo" class="container legal">
  <span class="eyebrow">Termos</span>
  <h1>Termos de Uso — Q One</h1>
  <p><strong>Última atualização:</strong> 27/10/2026</p>

  <p>Estes Termos regulam o acesso e o uso do Q One, plataforma de CRM e organização de leads. Ao criar uma conta, aceitar um convite ou utilizar a plataforma, você declara que leu, entendeu e concorda com estas condições.</p>

  <h2>1. Uso da plataforma</h2>
  <p>O Q One é uma plataforma para empresas e equipes organizarem leads, campos personalizados, agenda e informações comerciais. O acesso é pessoal e intransferível, e cada usuário deve manter suas credenciais em segurança.</p>

  <h2>2. Responsabilidade pelos dados inseridos</h2>
  <p>A organização contratante e seus usuários são os únicos responsáveis pelos dados que inserem, importam, coletam ou armazenam na plataforma, incluindo dados pessoais de leads, clientes e contatos.</p>
  <p>Você declara que possui base legal e autorização necessárias para coletar e tratar esses dados, e que as informações inseridas são verdadeiras, adequadas e atualizadas.</p>
  <p>O Q One atua exclusivamente como fornecedor de infraestrutura e armazenamento das informações inseridas pelo usuário. A plataforma não coleta dados de leads por conta própria, não define a finalidade do tratamento e não utiliza esses dados para finalidades próprias.</p>

  <h2>3. Responsabilidades do administrador da organização</h2>
  <p>O administrador é responsável por:</p>
  <ul>
    <li>gerenciar usuários, grupos e permissões de acesso;</li>
    <li>garantir que convites e acessos sejam enviados apenas a pessoas autorizadas;</li>
    <li>orientar sua equipe sobre o uso adequado da plataforma;</li>
    <li>atender solicitações de titulares de dados e obrigações legais aplicáveis;</li>
    <li>manter backups e cópias das informações que considerar necessárias.</li>
  </ul>

  <h2>4. Condutas proibidas</h2>
  <p>É proibido utilizar a plataforma para fins ilícitos, para armazenar conteúdo ilegal, para violar direitos de terceiros, para enviar comunicações não autorizadas ou para tentar acessar dados de outras organizações. O descumprimento pode resultar em suspensão ou encerramento da conta.</p>

  <h2>5. Disponibilidade e manutenção</h2>
  <p>Buscamos manter a plataforma disponível e segura, mas ela pode passar por manutenções, atualizações ou indisponibilidades temporárias. Não há garantia de operação ininterrupta ou livre de falhas.</p>

  <h2>6. Propriedade intelectual</h2>
  <p>O software, a marca, o layout e os conteúdos do Q One pertencem aos seus titulares. Os dados inseridos pela organização continuam pertencendo à organização, que concede ao Q One apenas a autorização necessária para armazená-los e processá-los conforme estes Termos.</p>

  <h2>7. Limitação de responsabilidade</h2>
  <p>O Q One não se responsabiliza pelo conteúdo, pela legalidade, pela exatidão ou pelo uso indevido das informações inseridas pelos usuários. A responsabilidade sobre os dados e sobre as decisões comerciais tomadas a partir deles é integralmente da organização e de seus usuários.</p>

  <h2>8. Encerramento e exclusão</h2>
  <p>A conta pode ser encerrada a qualquer momento. A organização pode solicitar a exportação ou a exclusão dos dados que inseriu, observadas obrigações legais, técnicas e de segurança. O Q One pode suspender acessos em caso de violação destes Termos ou de risco à plataforma.</p>

  <h2>9. Privacidade</h2>
  <p>O tratamento de dados pessoais pela Q One é descrito na <a href="<?= e(url('privacidade')) ?>">Política de Privacidade</a>. Dúvidas sobre dados ou solicitações de titulares podem ser enviadas para <a href="mailto:<?= e($config['site']['contact_email']) ?>"><?= e($config['site']['contact_email']) ?></a>.</p>

  <h2>10. Alterações</h2>
  <p>Estes Termos podem ser atualizados para refletir mudanças na plataforma ou na legislação. A data de atualização no topo indica a versão vigente. O uso contínuo após a publicação representa aceite das novas condições.</p>

  <h2>11. Legislação aplicável</h2>
  <p>Estes Termos são regidos pelas leis brasileiras. Eventuais controvérsias devem ser resolvidas no foro competente da comarca da sede da Q One, salvo disposição legal em contrário.</p>
</main>
<?php public_footer($config); ?>
