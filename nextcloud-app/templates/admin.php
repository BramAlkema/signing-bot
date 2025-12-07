<?php
/**
 * Admin settings template for DocuSeal Integration
 */

\OCP\Util::addScript('docuseal_integration', 'docuseal_integration-admin');
\OCP\Util::addStyle('docuseal_integration', 'docuseal_integration-admin');

/** @var array $_ */
?>

<div id="docuseal-admin-settings" class="section">
    <h2><?php p($l->t('DocuSeal Integration')); ?></h2>

    <!-- DocuSeal Settings -->
    <h3><?php p($l->t('DocuSeal Configuration')); ?></h3>
    <p class="settings-hint">
        <?php p($l->t('Configure the connection to your DocuSeal instance.')); ?>
    </p>

    <div class="form-group">
        <label for="docuseal-url">
            <?php p($l->t('DocuSeal URL')); ?>
        </label>
        <input
            type="url"
            id="docuseal-url"
            name="docuseal_url"
            value="<?php p($_['docuseal_url']); ?>"
            placeholder="https://your-docuseal-instance.com"
            class="input-wide"
        />
        <p class="hint">
            <?php p($l->t('The URL of your DocuSeal instance (e.g., https://api.docuseal.co or your self-hosted URL)')); ?>
        </p>
    </div>

    <div class="form-group">
        <label for="docuseal-api-key">
            <?php p($l->t('API Key')); ?>
        </label>
        <input
            type="password"
            id="docuseal-api-key"
            name="api_key"
            placeholder="<?php p($_['has_api_key'] ? '********' : $l->t('Enter your API key')); ?>"
            class="input-wide"
        />
        <p class="hint">
            <?php p($l->t('Your DocuSeal API key. Get it from your DocuSeal settings.')); ?>
        </p>
    </div>

    <div class="form-group">
        <label for="docuseal-webhook-secret">
            <?php p($l->t('Webhook Secret (optional)')); ?>
        </label>
        <input
            type="password"
            id="docuseal-webhook-secret"
            name="webhook_secret"
            placeholder="<?php p($l->t('Enter webhook secret for signature verification')); ?>"
            class="input-wide"
        />
    </div>

    <div class="form-group">
        <label><?php p($l->t('Webhook URL')); ?></label>
        <div class="webhook-url-display">
            <code id="webhook-url"><?php p($_['webhook_url']); ?></code>
            <button type="button" class="copy-btn" onclick="copyWebhookUrl()">
                <?php p($l->t('Copy')); ?>
            </button>
        </div>
        <p class="hint">
            <?php p($l->t('Configure this URL in DocuSeal to receive signing notifications.')); ?>
        </p>
    </div>

    <div class="form-group">
        <button type="button" id="docuseal-test-connection" class="button">
            <?php p($l->t('Test DocuSeal Connection')); ?>
        </button>
        <span id="docuseal-connection-status"></span>
    </div>

    <!-- Matrix Settings -->
    <h3><?php p($l->t('Matrix Configuration')); ?></h3>
    <p class="settings-hint">
        <?php p($l->t('Configure the connection to your Matrix homeserver for document signing rooms.')); ?>
    </p>

    <div class="form-group">
        <label for="matrix-homeserver">
            <?php p($l->t('Matrix Homeserver URL')); ?>
        </label>
        <input
            type="url"
            id="matrix-homeserver"
            name="matrix_homeserver"
            value="<?php p($_['matrix_homeserver'] ?? ''); ?>"
            placeholder="https://matrix.example.com"
            class="input-wide"
        />
        <p class="hint">
            <?php p($l->t('The URL of your Matrix homeserver (e.g., https://matrix.ctrlaltdef.eu)')); ?>
        </p>
    </div>

    <div class="form-group">
        <label for="matrix-access-token">
            <?php p($l->t('Bot Access Token')); ?>
        </label>
        <input
            type="password"
            id="matrix-access-token"
            name="matrix_access_token"
            placeholder="<?php p(($_['has_matrix_token'] ?? false) ? '********' : $l->t('Enter bot access token')); ?>"
            class="input-wide"
        />
        <p class="hint">
            <?php p($l->t('Access token for the signing bot account. Get it by logging in as the bot.')); ?>
        </p>
    </div>

    <div class="form-group">
        <label for="matrix-bot-user">
            <?php p($l->t('Bot User ID')); ?>
        </label>
        <input
            type="text"
            id="matrix-bot-user"
            name="matrix_bot_user"
            value="<?php p($_['matrix_bot_user'] ?? ''); ?>"
            placeholder="@docuseal-bot:matrix.example.com"
            class="input-wide"
        />
        <p class="hint">
            <?php p($l->t('The Matrix user ID of the signing bot (e.g., @docuseal-bot:matrix.ctrlaltdef.eu)')); ?>
        </p>
    </div>

    <div class="form-group">
        <button type="button" id="matrix-test-connection" class="button">
            <?php p($l->t('Test Matrix Connection')); ?>
        </button>
        <span id="matrix-connection-status"></span>
    </div>

    <!-- Signal Settings -->
    <h3><?php p($l->t('Signal Configuration')); ?></h3>
    <p class="settings-hint">
        <?php p($l->t('Configure Signal integration via signal-cli daemon.')); ?>
    </p>

    <div class="form-group">
        <input
            type="checkbox"
            id="signal-enabled"
            name="signal_enabled"
            class="checkbox"
            <?php if ($_['signal_enabled'] ?? false): ?>checked<?php endif; ?>
        />
        <label for="signal-enabled">
            <?php p($l->t('Enable Signal Integration')); ?>
        </label>
    </div>

    <div class="form-group">
        <label for="signal-phone-number">
            <?php p($l->t('Bot Phone Number')); ?>
        </label>
        <input
            type="tel"
            id="signal-phone-number"
            name="signal_phone_number"
            value="<?php p($_['signal_phone_number'] ?? ''); ?>"
            placeholder="+1234567890"
            class="input-wide"
        />
        <p class="hint">
            <?php p($l->t('The phone number registered with signal-cli for the bot.')); ?>
        </p>
    </div>

    <div class="form-group">
        <label for="signal-socket">
            <?php p($l->t('Signal CLI Socket')); ?>
        </label>
        <input
            type="text"
            id="signal-socket"
            name="signal_socket"
            value="<?php p($_['signal_socket'] ?? 'tcp://172.18.0.1:7583'); ?>"
            placeholder="tcp://172.18.0.1:7583"
            class="input-wide"
        />
        <p class="hint">
            <?php p($l->t('TCP or Unix socket path to signal-cli daemon (e.g., tcp://host:port or unix:///path/to/socket)')); ?>
        </p>
    </div>

    <div class="form-group">
        <button type="button" id="signal-test-connection" class="button">
            <?php p($l->t('Test Signal Connection')); ?>
        </button>
        <span id="signal-connection-status"></span>
    </div>

    <!-- Save Button -->
    <div class="form-group save-group">
        <button type="button" id="docuseal-save-settings" class="button primary">
            <?php p($l->t('Save All Settings')); ?>
        </button>
    </div>
</div>

<script>
function copyWebhookUrl() {
    const url = document.getElementById('webhook-url').textContent;
    navigator.clipboard.writeText(url).then(() => {
        OC.Notification.showTemporary(t('docuseal_integration', 'Webhook URL copied to clipboard'));
    });
}
</script>

<style>
#docuseal-admin-settings h3 {
    margin-top: 24px;
    margin-bottom: 8px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 8px;
}

#docuseal-admin-settings h3:first-of-type {
    margin-top: 16px;
}

#docuseal-admin-settings .form-group {
    margin-bottom: 16px;
}

#docuseal-admin-settings label {
    display: block;
    font-weight: bold;
    margin-bottom: 4px;
}

#docuseal-admin-settings .input-wide {
    width: 400px;
    max-width: 100%;
}

#docuseal-admin-settings .hint {
    color: var(--color-text-lighter);
    font-size: 0.9em;
    margin-top: 4px;
}

#docuseal-admin-settings .webhook-url-display {
    display: flex;
    align-items: center;
    gap: 8px;
}

#docuseal-admin-settings .webhook-url-display code {
    background: var(--color-background-dark);
    padding: 8px 12px;
    border-radius: var(--border-radius);
    font-family: monospace;
    word-break: break-all;
}

#docuseal-admin-settings .copy-btn {
    padding: 8px 12px;
}

#docuseal-admin-settings .save-group {
    margin-top: 24px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
}

.success {
    color: var(--color-success);
}

.error {
    color: var(--color-error);
}
</style>
