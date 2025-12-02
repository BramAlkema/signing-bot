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
            <?php p($l->t('Test Connection')); ?>
        </button>
        <span id="connection-status"></span>
    </div>

    <div class="form-group">
        <button type="button" id="docuseal-save-settings" class="button primary">
            <?php p($l->t('Save')); ?>
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

#connection-status.success {
    color: var(--color-success);
}

#connection-status.error {
    color: var(--color-error);
}
</style>
