<?php
// Isolated regression tests. No production config is changed and no email is sent.
declare(strict_types=1);
$source = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/qeai-test-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
define('ROOT_PATH', $fixture);
require $source . '/app/config_store.php';
require $source . '/app/security.php';
require $source . '/app/mailer.php';
$checks = 0;
$process = null;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
    echo 'PASS: ' . $label . PHP_EOL;
}
function copy_tree(string $from, string $to): void {
    if (is_dir($from)) {
        if (!is_dir($to)) mkdir($to, 0700, true);
        foreach (scandir($from) as $name) if ($name !== '.' && $name !== '..') copy_tree($from . '/' . $name, $to . '/' . $name);
    } else copy($from, $to);
}
function clean_tree(string $path): void {
    if (is_dir($path)) {
        foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') clean_tree($path . '/' . $name);
        rmdir($path);
    } else unlink($path);
}
$cookies = [];
function request(string $path, ?array $post = null): array {
    global $base, $cookies;
    $headers = ['Cookie: ' . implode('; ', array_map(fn($key, $value) => $key . '=' . $value, array_keys($cookies), $cookies))];
    if ($post !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $context = stream_context_create(['http' => ['method' => $post === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $post === null ? '' : http_build_query($post), 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
    $body = file_get_contents($base . $path, false, $context);
    foreach ($http_response_header as $header) if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $match)) $cookies[$match[1]] = $match[2];
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $status);
    return ['status' => (int)$status[1], 'body' => (string)$body, 'headers' => $http_response_header];
}
function token(array $response): string {
    if (!preg_match('/name="csrf" value="([a-f0-9]+)"/', $response['body'], $match)) throw new RuntimeException('CSRF token missing');
    return $match[1];
}
try {
    ensure_storage_directory('');
    ensure_storage_directory('rate-limit');
    check(is_dir(ROOT_PATH . '/storage/rate-limit'), 'missing storage directories are created');
    check(load_config()['admin']['password_hash'] === '', 'fresh defaults load');
    check(cnpj_is_valid('60357082000158') && format_cnpj('60357082000158') === '60.357.082/0001-58' && !cnpj_is_valid('123'), 'CNPJ validation and formatting');
    check(save_config(default_config()) && load_config() === default_config(), 'atomic config save and reload');
    file_put_contents(config_path(), '{broken');
    try { load_config(); check(false, 'corrupt config must fail closed'); } catch (RuntimeException $error) { check(str_contains($error->getMessage(), 'inválido'), 'corrupt config cannot reopen installation'); }
    save_config(default_config());
    mkdir(ROOT_PATH . '/config');
    file_put_contents(ROOT_PATH . '/config/app.key', bin2hex(random_bytes(32)));
    file_put_contents(ROOT_PATH . '/config/bootstrap.key', 'test-only-key');
    $encrypted = encrypt_secret('example-test-password');
    check(decrypt_secret($encrypted) === 'example-test-password' && $encrypted !== 'example-test-password', 'SMTP encryption round trip');
    check(text_length(str_repeat('á', 4000)) === 4000 && text_length("a\nb") === 3, 'Unicode and multiline message lengths');
    $_SESSION = ['csrf' => str_repeat('a', 64)];
    check(!verify_csrf(['invalid']) && verify_csrf(str_repeat('a', 64)), 'CSRF rejects malformed arrays safely');
    check(rate_limit('unit', 2, 900) && rate_limit('unit', 2, 900) && !rate_limit('unit', 2, 900), 'rate limit blocks requests over quota');
    foreach (['app', 'assets', 'workspace', 'index.php', 'contact.php', 'privacy.php', 'cookies.php'] as $file) copy_tree($source . '/' . $file, ROOT_PATH . '/' . $file);

    // Banco de teste dedicado (painel: usuários/grupos; blog).
    $testDbName = 'qeai_suite_test_' . bin2hex(random_bytes(4));
    $pdoRoot = new PDO('mysql:host=127.0.0.1', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdoRoot->exec('DROP DATABASE IF EXISTS ' . $testDbName);
    $pdoRoot->exec('CREATE DATABASE ' . $testDbName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdoRoot = new PDO('mysql:host=127.0.0.1;dbname=' . $testDbName, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    foreach (preg_split('/;\s*\r?\n/', (string)file_get_contents($source . '/app/blog_install.sql')) ?: [] as $chunk) {
        $lines = array_filter(preg_split('/\r?\n/', $chunk) ?: [], fn($line) => !preg_match('/^\s*--/', $line));
        $statement = trim(implode("\n", $lines));
        if ($statement !== '') $pdoRoot->exec($statement);
    }
    $pdoRoot = null;
    file_put_contents(ROOT_PATH . '/config/db.php', "<?php return ['host'=>'127.0.0.1','port'=>3306,'database'=>'$testDbName','username'=>'root','password'=>'','charset'=>'utf8mb4'];\n");
    require $source . '/app/auth.php';

    $listener = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $base = 'http://' . $address;
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'sys_temp_dir=' . ROOT_PATH, '-S', $address, '-t', ROOT_PATH], [0 => ['pipe', 'r'], 1 => ['file', ROOT_PATH . '/server.log', 'a'], 2 => ['file', ROOT_PATH . '/server.log', 'a']], $pipes, ROOT_PATH);
    if (!is_resource($process)) throw new RuntimeException('Unable to start test PHP server');
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $socket = @stream_socket_client('tcp://' . $address, $errno, $errstr, .1);
        if ($socket) { fclose($socket); break; }
        usleep(100000);
    }
    $home = request('/');
    check($home['status'] === 200 && str_contains($home['body'], 'Fale sobre seu projeto') && str_contains($home['body'], 'CNPJ 60.357.082/0001-58'), 'home loads with PHP sessions and footer CNPJ');
    check(!preg_match('/(?:href|action)="[^\"]+\.php/', $home['body']) && str_contains($home['body'], 'action="contato"') && str_contains($home['body'], 'rel="canonical" href="https://qeai.com.br/"'), 'public navigation, form and canonical use clean URLs');
    check(request('/privacy.php')['status'] === 200 && request('/cookies.php')['status'] === 200, 'legal pages load');
    $csrf = token($home);
    $bad = request('/contact.php', ['csrf' => ['bad'], 'name' => 'Example']);
    $feedback = request('/index.php');
    check($bad['status'] === 303 && str_contains($feedback['body'], 'Sua sessão expirou') && str_contains($feedback['body'], 'value="Example"'), 'expired CSRF preserves form values');
    $bad = request('/contact.php', ['csrf' => $csrf, 'name' => '<script>example</script>', 'email' => 'invalid', 'message' => 'Testing']);
    $feedback = request('/index.php');
    check($bad['status'] === 303 && str_contains($feedback['body'], 'email-error') && str_contains($feedback['body'], 'privacy-error') && str_contains($feedback['body'], '&lt;script&gt;example&lt;/script&gt;'), 'field validation, consent and output escaping');
    $smtpResult = request('/contact.php', ['csrf' => $csrf, 'name' => 'Example', 'email' => 'example@example.test', 'message' => str_repeat('á', 4000), 'privacy' => '1']);
    $feedback = request('/index.php');
    check(str_contains($feedback['body'], 'Não foi possível enviar agora') && !str_contains($feedback['body'], 'id="message-error"') && str_contains($feedback['body'], 'example@example.test'), 'unconfigured SMTP fails gracefully and preserves accented text');
    $setup = request('/workspace/');
    check(str_contains($setup['body'], 'Vamos preparar seu painel'), 'fresh installation screen');
    $setupToken = token($setup);
    // Simulate an omitted installation key, a common incomplete-upload problem.
    file_put_contents(ROOT_PATH . '/config/bootstrap.key', '');
    $blocked = request('/workspace/index.php', ['csrf' => $setupToken, 'action' => 'setup', 'setup_key' => '', 'name' => 'Tester', 'email' => 'tester@example.test', 'password' => 'local-test-password']);
    check(str_contains($blocked['body'], 'Chave de instalação inválida') && !auth_installed(), 'empty bootstrap key cannot create an admin');
    file_put_contents(ROOT_PATH . '/config/bootstrap.key', 'test-only-key');
    $installed = request('/workspace/index.php', ['csrf' => $setupToken, 'action' => 'setup', 'setup_key' => 'test-only-key', 'name' => 'Tester', 'email' => 'tester@example.test', 'password' => 'local-test-password']);
    check($installed['status'] === 303 && auth_installed(), 'installation creates the ADM by e-mail');
    unlink(ROOT_PATH . '/storage/installed.lock');
    $login = request('/workspace/');
    check(str_contains($login['body'], 'Bem-vindo de volta') && !str_contains($login['body'], 'Vamos preparar seu painel'), 'missing marker cannot reopen installation');
    $loggedIn = request('/workspace/index.php', ['csrf' => token($login), 'action' => 'login', 'email' => 'tester@example.test', 'password' => 'local-test-password']);
    $panel = request('/workspace/');
    check($loggedIn['status'] === 303 && str_contains($panel['body'], 'Saúde da instalação'), 'login and authenticated panel');
    $save = request('/workspace/index.php', ['csrf' => token($panel), 'action' => 'save', 'contact_email' => 'hello@example.test', 'smtp_username' => 'hello@example.test', 'to_email' => 'hello@example.test', 'smtp_host' => 'smtp.example.test', 'smtp_port' => '465', 'smtp_encryption' => 'ssl', 'from_name' => 'Test', 'primary' => '#123456', 'cnpj' => '60.357.082/0001-58']);
    check($save['status'] === 303 && load_config()['theme']['primary'] === '#123456' && !load_config()['features']['contact_form'] && load_config()['site']['cnpj'] === '60357082000158', 'authenticated settings save and disable contact');
    $panel = request('/workspace/');
    $badCnpj = request('/workspace/index.php', ['csrf' => token($panel), 'action' => 'save', 'contact_email' => 'hello@example.test', 'smtp_username' => 'hello@example.test', 'to_email' => 'hello@example.test', 'smtp_host' => 'smtp.example.test', 'smtp_port' => '465', 'smtp_encryption' => 'ssl', 'from_name' => 'Test', 'cnpj' => '123']);
    check(str_contains($badCnpj['body'], 'CNPJ válido') && load_config()['site']['cnpj'] === '60357082000158', 'invalid CNPJ is rejected');
    $disabled = request('/contact.php', ['csrf' => $csrf, 'name' => 'Example']);
    check($disabled['status'] === 303 && !str_contains(request('/')['body'], 'data-contact-form'), 'disabled form cannot send');
    $panel = request('/workspace/');
    $admin = auth_user_by_email('tester@example.test');
    $wrongPassword = request('/workspace/index.php', ['csrf' => token($panel), 'action' => 'password', 'current_password' => 'wrong', 'new_password' => 'new-local-test-password']);
    check(str_contains($wrongPassword['body'], 'senha atual está incorreta') && password_verify('local-test-password', (string)$admin['password_hash']), 'password change requires current password');
    $changePassword = request('/workspace/index.php', ['csrf' => token($panel), 'action' => 'password', 'current_password' => 'local-test-password', 'new_password' => 'new-local-test-password']);
    check($changePassword['status'] === 303 && password_verify('new-local-test-password', (string)auth_user_by_email('tester@example.test')['password_hash']), 'password update persists');

    // Usuários e grupos de acesso
    $admCookies = $cookies;
    $usersPage = request('/workspace/users.php');
    check(str_contains($usersPage['body'], 'Usuários e grupos') && str_contains($usersPage['body'], 'Grupos de acesso'), 'ADM manages users and roles');
    $escritorId = 0;
    foreach (auth_roles() as $role) if ($role['slug'] === 'escritor') $escritorId = (int)$role['id'];
    $created = request('/workspace/users.php', ['csrf' => token($usersPage), 'action' => 'create_user', 'name' => 'Escritor Teste', 'email' => 'escritor@example.test', 'role_id' => (string)$escritorId, 'access' => 'password', 'password' => '']);
    check($created['status'] === 303 && $escritorId > 0, 'ADM creates a writer user');
    $afterCreate = request('/workspace/users.php');
    preg_match('/password-reveal">([^<]+)</', $afterCreate['body'], $passMatch);
    $writerPass = trim($passMatch[1] ?? '');
    check($writerPass !== '', 'temporary password is shown once');

    $cookies = [];
    $writerLogin = request('/workspace/');
    $writerLogged = request('/workspace/index.php', ['csrf' => token($writerLogin), 'action' => 'login', 'email' => 'escritor@example.test', 'password' => $writerPass]);
    check($writerLogged['status'] === 303, 'writer logs in with temporary password');
    $writerPanel = request('/workspace/');
    check(str_contains($writerPanel['body'], 'Defina sua senha'), 'writer must change the temporary password');
    $writerChange = request('/workspace/index.php', ['csrf' => token($writerPanel), 'action' => 'password', 'current_password' => $writerPass, 'new_password' => 'writer-local-pass-123']);
    check($writerChange['status'] === 303, 'writer changes password');
    $writerPanel = request('/workspace/');
    check(!str_contains($writerPanel['body'], 'Saúde da instalação') && !str_contains($writerPanel['body'], 'Usuários e grupos'), 'writer has no settings or users');
    $writerBlog = request('/workspace/blog.php');
    check($writerBlog['status'] === 200 && str_contains($writerBlog['body'], 'Posts do blog'), 'writer can open the blog');
    $writerUsers = request('/workspace/users.php');
    check($writerUsers['status'] === 403, 'writer cannot open users (403)');
    $writerSettings = request('/workspace/blog.php?view=settings');
    check($writerSettings['status'] === 403, 'writer cannot open blog settings (403)');
    $cookies = $admCookies;

    // Convite por link
    $convidadoId = 0;
    foreach (auth_roles() as $role) if ($role['slug'] === 'convidado') $convidadoId = (int)$role['id'];
    $usersPage2 = request('/workspace/users.php');
    $invited = request('/workspace/users.php', ['csrf' => token($usersPage2), 'action' => 'create_user', 'name' => 'Convidado Link', 'email' => 'convite@example.test', 'role_id' => (string)$convidadoId, 'access' => 'invite', 'password' => '']);
    check($invited['status'] === 303, 'ADM invites a user by link');
    $invitePage = request('/workspace/users.php');
    preg_match('/password-reveal">([^<]+)</', $invitePage['body'], $inviteMatch);
    parse_str((string)parse_url(trim($inviteMatch[1] ?? ''), PHP_URL_QUERY), $inviteQuery);
    $inviteToken = $inviteQuery['token'] ?? '';
    check($inviteToken !== '', 'invite link is shown once');

    $admCookies2 = $cookies;
    $cookies = [];
    $tokenPage = request('/workspace/?token=' . $inviteToken);
    check(str_contains($tokenPage['body'], 'Crie sua nova senha'), 'invite link opens set-password screen');
    $setPass = request('/workspace/index.php', ['csrf' => token($tokenPage), 'action' => 'set_password', 'token' => $inviteToken, 'new_password' => 'convidado-link-pass', 'confirm_password' => 'convidado-link-pass']);
    check($setPass['status'] === 303, 'invited user sets password');
    $cookies = [];
    $inviteLogin = request('/workspace/');
    $inviteLogged = request('/workspace/index.php', ['csrf' => token($inviteLogin), 'action' => 'login', 'email' => 'convite@example.test', 'password' => 'convidado-link-pass']);
    check($inviteLogged['status'] === 303, 'invited user can log in');
    $cookies = $admCookies2;

    // Recuperação de senha e token inválido
    $forgotPage = request('/workspace/');
    $forgot = request('/workspace/index.php', ['csrf' => token($forgotPage), 'action' => 'forgot', 'email' => 'escritor@example.test']);
    check($forgot['status'] === 303 && (int)db()->query("SELECT COUNT(*) FROM user_tokens WHERE purpose = 'reset' AND used_at IS NULL")->fetchColumn() > 0, 'password reset creates a token');
    $cookies = [];
    $badToken = request('/workspace/?token=invalido');
    check(str_contains($badToken['body'], 'Link inválido'), 'invalid token is rejected');
    $cookies = $admCookies2;

    $panel = request('/workspace/');
    $logout = request('/workspace/logout.php');
    check($logout['status'] === 303 && str_contains(request('/workspace/')['body'], 'Saúde da instalação'), 'GET cannot log the administrator out');
    $logout = request('/workspace/logout.php', ['csrf' => token($panel)]);
    check($logout['status'] === 303 && str_contains(request('/workspace/')['body'], 'Bem-vindo de volta'), 'POST logout ends session');
    echo "\nAll {$checks} checks passed. No real emails were sent.\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if (isset($testDbName)) {
        try { $drop = new PDO('mysql:host=127.0.0.1', 'root', ''); $drop->exec('DROP DATABASE IF EXISTS ' . $testDbName); } catch (Throwable $exception) {}
    }
    $resolved = realpath($fixture);
    $tempRoot = realpath(sys_get_temp_dir());
    if ($resolved !== false && dirname($resolved) === $tempRoot && str_starts_with(basename($resolved), 'qeai-test-')) clean_tree($resolved);
}



