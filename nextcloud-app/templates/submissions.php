<?php
/**
 * Submissions view template for DocuSeal Integration app
 */

\OCP\Util::addScript('docuseal_integration', 'docuseal_integration-main');
\OCP\Util::addStyle('docuseal_integration', 'docuseal_integration-main');
?>

<div id="content" class="app-docuseal_integration">
    <div id="app-content">
        <!-- Vue.js submissions view will mount here -->
        <div id="docuseal-submissions"></div>
    </div>
</div>
