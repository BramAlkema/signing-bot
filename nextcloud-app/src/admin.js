/**
 * Admin settings JavaScript
 */

import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import '@nextcloud/dialogs/style.css'

document.addEventListener('DOMContentLoaded', () => {
    const saveButton = document.getElementById('docuseal-save-settings')
    const testButton = document.getElementById('docuseal-test-connection')
    const statusSpan = document.getElementById('connection-status')

    if (saveButton) {
        saveButton.addEventListener('click', async () => {
            const docusealUrl = document.getElementById('docuseal-url').value
            const apiKey = document.getElementById('docuseal-api-key').value
            const webhookSecret = document.getElementById('docuseal-webhook-secret').value

            try {
                saveButton.disabled = true
                saveButton.textContent = t('docuseal_integration', 'Saving...')

                const response = await axios.put(
                    generateUrl('/apps/docuseal_integration/api/settings'),
                    {
                        docuseal_url: docusealUrl,
                        api_key: apiKey || undefined,
                        webhook_secret: webhookSecret || undefined,
                    }
                )

                showSuccess(t('docuseal_integration', 'Settings saved'))

                if (response.data.connection_status) {
                    statusSpan.textContent = t('docuseal_integration', 'Connected')
                    statusSpan.className = 'success'
                }
            } catch (error) {
                console.error('Failed to save settings:', error)
                showError(t('docuseal_integration', 'Failed to save settings'))
            } finally {
                saveButton.disabled = false
                saveButton.textContent = t('docuseal_integration', 'Save')
            }
        })
    }

    if (testButton) {
        testButton.addEventListener('click', async () => {
            try {
                testButton.disabled = true
                statusSpan.textContent = t('docuseal_integration', 'Testing...')
                statusSpan.className = ''

                const response = await axios.get(
                    generateUrl('/apps/docuseal_integration/api/settings')
                )

                if (response.data.connection_status) {
                    statusSpan.textContent = t('docuseal_integration', 'Connection successful!')
                    statusSpan.className = 'success'
                    showSuccess(t('docuseal_integration', 'Connection to DocuSeal successful'))
                } else {
                    statusSpan.textContent = t('docuseal_integration', 'Connection failed')
                    statusSpan.className = 'error'
                    showError(t('docuseal_integration', 'Could not connect to DocuSeal. Check your settings.'))
                }
            } catch (error) {
                console.error('Connection test failed:', error)
                statusSpan.textContent = t('docuseal_integration', 'Connection failed')
                statusSpan.className = 'error'
                showError(t('docuseal_integration', 'Connection test failed'))
            } finally {
                testButton.disabled = false
            }
        })
    }
})
