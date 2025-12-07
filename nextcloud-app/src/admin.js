/**
 * Admin settings JavaScript
 */

import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import '@nextcloud/dialogs/style.css'

document.addEventListener('DOMContentLoaded', () => {
    const saveButton = document.getElementById('docuseal-save-settings')
    const docusealTestButton = document.getElementById('docuseal-test-connection')
    const matrixTestButton = document.getElementById('matrix-test-connection')
    const signalTestButton = document.getElementById('signal-test-connection')
    const docusealStatusSpan = document.getElementById('docuseal-connection-status')
    const matrixStatusSpan = document.getElementById('matrix-connection-status')
    const signalStatusSpan = document.getElementById('signal-connection-status')

    if (saveButton) {
        saveButton.addEventListener('click', async () => {
            // DocuSeal settings
            const docusealUrl = document.getElementById('docuseal-url').value
            const apiKey = document.getElementById('docuseal-api-key').value
            const webhookSecret = document.getElementById('docuseal-webhook-secret').value

            // Matrix settings
            const matrixHomeserver = document.getElementById('matrix-homeserver').value
            const matrixAccessToken = document.getElementById('matrix-access-token').value
            const matrixBotUser = document.getElementById('matrix-bot-user').value

            // Signal settings
            const signalEnabled = document.getElementById('signal-enabled')?.checked
            const signalPhoneNumber = document.getElementById('signal-phone-number')?.value
            const signalSocket = document.getElementById('signal-socket')?.value

            try {
                saveButton.disabled = true
                saveButton.textContent = t('docuseal_integration', 'Saving...')

                const response = await axios.put(
                    generateUrl('/apps/docuseal_integration/api/settings'),
                    {
                        docuseal_url: docusealUrl,
                        api_key: apiKey || undefined,
                        webhook_secret: webhookSecret || undefined,
                        matrix_homeserver: matrixHomeserver,
                        matrix_access_token: matrixAccessToken || undefined,
                        matrix_bot_user: matrixBotUser,
                        signal_enabled: signalEnabled,
                        signal_phone_number: signalPhoneNumber,
                        signal_socket: signalSocket,
                    }
                )

                showSuccess(t('docuseal_integration', 'Settings saved'))

                // Update DocuSeal status
                if (response.data.docuseal_connected) {
                    docusealStatusSpan.textContent = t('docuseal_integration', 'Connected')
                    docusealStatusSpan.className = 'success'
                }

                // Update Matrix status
                if (response.data.matrix_connected?.connected) {
                    matrixStatusSpan.textContent = t('docuseal_integration', 'Connected as {user}', {
                        user: response.data.matrix_connected.user_id || 'bot'
                    })
                    matrixStatusSpan.className = 'success'
                }
            } catch (error) {
                console.error('Failed to save settings:', error)
                showError(t('docuseal_integration', 'Failed to save settings'))
            } finally {
                saveButton.disabled = false
                saveButton.textContent = t('docuseal_integration', 'Save All Settings')
            }
        })
    }

    if (docusealTestButton) {
        docusealTestButton.addEventListener('click', async () => {
            try {
                docusealTestButton.disabled = true
                docusealStatusSpan.textContent = t('docuseal_integration', 'Testing...')
                docusealStatusSpan.className = ''

                const response = await axios.post(
                    generateUrl('/apps/docuseal_integration/api/settings/test-docuseal')
                )

                if (response.data.connected) {
                    docusealStatusSpan.textContent = t('docuseal_integration', 'Connection successful!')
                    docusealStatusSpan.className = 'success'
                    showSuccess(t('docuseal_integration', 'Connection to DocuSeal successful'))
                } else {
                    docusealStatusSpan.textContent = t('docuseal_integration', 'Connection failed')
                    docusealStatusSpan.className = 'error'
                    showError(t('docuseal_integration', 'Could not connect to DocuSeal. Check your settings.'))
                }
            } catch (error) {
                console.error('DocuSeal connection test failed:', error)
                docusealStatusSpan.textContent = t('docuseal_integration', 'Connection failed')
                docusealStatusSpan.className = 'error'
                showError(t('docuseal_integration', 'DocuSeal connection test failed'))
            } finally {
                docusealTestButton.disabled = false
            }
        })
    }

    if (matrixTestButton) {
        matrixTestButton.addEventListener('click', async () => {
            try {
                matrixTestButton.disabled = true
                matrixStatusSpan.textContent = t('docuseal_integration', 'Testing...')
                matrixStatusSpan.className = ''

                const response = await axios.post(
                    generateUrl('/apps/docuseal_integration/api/settings/test-matrix')
                )

                if (response.data.connected) {
                    matrixStatusSpan.textContent = t('docuseal_integration', 'Connected as {user}', {
                        user: response.data.user_id || 'bot'
                    })
                    matrixStatusSpan.className = 'success'
                    showSuccess(t('docuseal_integration', 'Connection to Matrix successful'))
                } else {
                    matrixStatusSpan.textContent = t('docuseal_integration', 'Connection failed: {error}', {
                        error: response.data.error || 'Unknown error'
                    })
                    matrixStatusSpan.className = 'error'
                    showError(t('docuseal_integration', 'Could not connect to Matrix. Check your settings.'))
                }
            } catch (error) {
                console.error('Matrix connection test failed:', error)
                matrixStatusSpan.textContent = t('docuseal_integration', 'Connection failed')
                matrixStatusSpan.className = 'error'
                showError(t('docuseal_integration', 'Matrix connection test failed'))
            } finally {
                matrixTestButton.disabled = false
            }
        })
    }

    if (signalTestButton) {
        signalTestButton.addEventListener('click', async () => {
            try {
                signalTestButton.disabled = true
                signalStatusSpan.textContent = t('docuseal_integration', 'Testing...')
                signalStatusSpan.className = ''

                const response = await axios.post(
                    generateUrl('/apps/docuseal_integration/api/settings/test-signal')
                )

                if (response.data.connected) {
                    signalStatusSpan.textContent = t('docuseal_integration', 'Connected (signal-cli v{version})', {
                        version: response.data.version || 'unknown'
                    })
                    signalStatusSpan.className = 'success'
                    showSuccess(t('docuseal_integration', 'Connection to Signal successful'))
                } else {
                    signalStatusSpan.textContent = t('docuseal_integration', 'Connection failed: {error}', {
                        error: response.data.error || 'Unknown error'
                    })
                    signalStatusSpan.className = 'error'
                    showError(t('docuseal_integration', 'Could not connect to Signal. Check your settings.'))
                }
            } catch (error) {
                console.error('Signal connection test failed:', error)
                signalStatusSpan.textContent = t('docuseal_integration', 'Connection failed')
                signalStatusSpan.className = 'error'
                showError(t('docuseal_integration', 'Signal connection test failed'))
            } finally {
                signalTestButton.disabled = false
            }
        })
    }
})
