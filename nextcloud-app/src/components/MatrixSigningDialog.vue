<template>
    <NcModal
        :show="show"
        size="large"
        :title="t('docuseal_integration', 'Matrix Document Signing')"
        @close="close">
        <div class="matrix-signing-dialog">
            <!-- Step 1: Setup -->
            <div v-if="step === 'setup'" class="step-setup">
                <h2>{{ t('docuseal_integration', 'Create Signing Session') }}</h2>

                <div class="file-info">
                    <span class="icon-file"></span>
                    <strong>{{ fileName }}</strong>
                </div>

                <div class="form-group">
                    <label>{{ t('docuseal_integration', 'Add Signers (Matrix IDs)') }}</label>
                    <div
                        v-for="(signer, index) in signers"
                        :key="index"
                        class="signer-row">
                        <NcTextField
                            :value.sync="signer.matrixId"
                            :label="t('docuseal_integration', 'Matrix ID')"
                            :placeholder="t('docuseal_integration', '@user:server.com')"
                        />
                        <NcButton
                            v-if="signers.length > 1"
                            type="tertiary"
                            @click="removeSigner(index)">
                            <template #icon>
                                <span class="icon-delete"></span>
                            </template>
                        </NcButton>
                    </div>
                    <NcButton type="secondary" @click="addSigner">
                        <template #icon>
                            <span class="icon-add"></span>
                        </template>
                        {{ t('docuseal_integration', 'Add signer') }}
                    </NcButton>
                </div>

                <div class="info-box">
                    <h4>{{ t('docuseal_integration', 'How Matrix Signing Works') }}</h4>
                    <ol>
                        <li>{{ t('docuseal_integration', 'A private Matrix room is created') }}</li>
                        <li>{{ t('docuseal_integration', 'All signers verify each other\'s identity (emoji verification)') }}</li>
                        <li>{{ t('docuseal_integration', 'Each signer downloads and reviews the document') }}</li>
                        <li>{{ t('docuseal_integration', 'Each signer signs the document hash with their SSH/GPG key') }}</li>
                        <li>{{ t('docuseal_integration', 'Signatures are posted to the room as proof') }}</li>
                    </ol>
                </div>

                <div class="dialog-actions">
                    <NcButton type="secondary" @click="close">
                        {{ t('docuseal_integration', 'Cancel') }}
                    </NcButton>
                    <NcButton
                        type="primary"
                        :disabled="!isValid || loading"
                        @click="createSession">
                        <template #icon>
                            <NcLoadingIcon v-if="loading" :size="20" />
                        </template>
                        {{ t('docuseal_integration', 'Create Signing Room') }}
                    </NcButton>
                </div>
            </div>

            <!-- Step 2: Instructions -->
            <div v-if="step === 'instructions'" class="step-instructions">
                <h2>{{ t('docuseal_integration', 'Signing Session Created') }}</h2>

                <div class="success-box">
                    <span class="icon-checkmark"></span>
                    {{ t('docuseal_integration', 'Matrix room created and document uploaded') }}
                </div>

                <div class="session-info">
                    <p><strong>{{ t('docuseal_integration', 'Room ID:') }}</strong> {{ session.room_id }}</p>
                    <p><strong>{{ t('docuseal_integration', 'Document Hash:') }}</strong></p>
                    <code class="hash">{{ session.document_hash }}</code>
                </div>

                <div class="instructions-box">
                    <h3>{{ t('docuseal_integration', 'Signing Instructions') }}</h3>

                    <div class="instruction-tabs">
                        <button
                            :class="{ active: instructionTab === 'ssh' }"
                            @click="instructionTab = 'ssh'">
                            SSH Key
                        </button>
                        <button
                            :class="{ active: instructionTab === 'gpg' }"
                            @click="instructionTab = 'gpg'">
                            GPG Key
                        </button>
                    </div>

                    <div v-if="instructionTab === 'ssh'" class="instruction-content">
                        <p>{{ t('docuseal_integration', 'Sign the document hash with your SSH key:') }}</p>
                        <pre><code>echo -n '{{ session.document_hash }}' > /tmp/hash.txt
ssh-keygen -Y sign -f ~/.ssh/id_ed25519 -n document /tmp/hash.txt</code></pre>
                        <p>{{ t('docuseal_integration', 'Then paste the contents of /tmp/hash.txt.sig below.') }}</p>
                    </div>

                    <div v-if="instructionTab === 'gpg'" class="instruction-content">
                        <p>{{ t('docuseal_integration', 'Sign the document hash with your GPG key:') }}</p>
                        <pre><code>echo -n '{{ session.document_hash }}' | gpg --armor --detach-sign</code></pre>
                        <p>{{ t('docuseal_integration', 'Then paste the output below.') }}</p>
                    </div>
                </div>

                <div class="form-group">
                    <label>{{ t('docuseal_integration', 'Your Matrix ID') }}</label>
                    <NcTextField
                        :value.sync="myMatrixId"
                        :placeholder="t('docuseal_integration', '@you:server.com')"
                    />
                </div>

                <div class="form-group">
                    <label>{{ t('docuseal_integration', 'Your Public Key') }}</label>
                    <NcTextArea
                        v-model="publicKey"
                        :placeholder="t('docuseal_integration', 'ssh-ed25519 AAAA... or GPG public key')"
                        rows="3"
                    />
                </div>

                <div class="form-group">
                    <label>{{ t('docuseal_integration', 'Your Signature') }}</label>
                    <NcTextArea
                        v-model="signature"
                        :placeholder="t('docuseal_integration', 'Paste your signature here')"
                        rows="6"
                    />
                </div>

                <div class="dialog-actions">
                    <NcButton type="secondary" @click="close">
                        {{ t('docuseal_integration', 'Close') }}
                    </NcButton>
                    <NcButton
                        type="primary"
                        :disabled="!canSubmitSignature || submitting"
                        @click="submitSignature">
                        <template #icon>
                            <NcLoadingIcon v-if="submitting" :size="20" />
                        </template>
                        {{ t('docuseal_integration', 'Submit Signature') }}
                    </NcButton>
                </div>
            </div>

            <!-- Step 3: Status -->
            <div v-if="step === 'status'" class="step-status">
                <h2>{{ t('docuseal_integration', 'Signing Status') }}</h2>

                <div class="document-info">
                    <p><strong>{{ session.document_name }}</strong></p>
                    <code class="hash">{{ session.document_hash }}</code>
                </div>

                <div class="signatures-list">
                    <h3>{{ t('docuseal_integration', 'Signatures') }}</h3>

                    <div
                        v-for="sig in signatures"
                        :key="sig.event_id"
                        :class="['signature-item', sig.verified ? 'verified' : 'invalid']">
                        <div class="sig-header">
                            <span class="signer">{{ sig.signer }}</span>
                            <span class="status">
                                {{ sig.verified ? '✓ Verified' : '✗ Invalid' }}
                            </span>
                        </div>
                        <div class="sig-details">
                            <span>{{ sig.key_type }}</span>
                            <span>{{ formatDate(sig.signed_at) }}</span>
                        </div>
                    </div>

                    <div v-if="missingSigners.length > 0" class="missing-signers">
                        <h4>{{ t('docuseal_integration', 'Awaiting signatures from:') }}</h4>
                        <ul>
                            <li v-for="signer in missingSigners" :key="signer">{{ signer }}</li>
                        </ul>
                    </div>
                </div>

                <div v-if="allSigned" class="complete-box">
                    <span class="icon-checkmark-white"></span>
                    <strong>{{ t('docuseal_integration', 'Document fully signed!') }}</strong>
                    <p>{{ t('docuseal_integration', 'All required parties have signed. The signatures are stored in the Matrix room as an immutable audit trail.') }}</p>
                </div>

                <div class="dialog-actions">
                    <NcButton type="secondary" @click="close">
                        {{ t('docuseal_integration', 'Close') }}
                    </NcButton>
                    <NcButton type="secondary" @click="refreshStatus">
                        {{ t('docuseal_integration', 'Refresh') }}
                    </NcButton>
                </div>
            </div>
        </div>
    </NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'

export default {
    name: 'MatrixSigningDialog',
    components: {
        NcModal,
        NcButton,
        NcTextField,
        NcTextArea,
        NcLoadingIcon,
    },
    props: {
        filePath: {
            type: String,
            required: true,
        },
        fileName: {
            type: String,
            required: true,
        },
    },
    data() {
        return {
            show: true,
            step: 'setup', // setup, instructions, status
            loading: false,
            submitting: false,
            signers: [{ matrixId: '' }],
            session: null,
            signatures: [],
            missingSigners: [],
            allSigned: false,
            instructionTab: 'ssh',
            myMatrixId: '',
            publicKey: '',
            signature: '',
        }
    },
    computed: {
        isValid() {
            return this.signers.some(s => this.isValidMatrixId(s.matrixId))
        },
        canSubmitSignature() {
            return this.myMatrixId && this.publicKey && this.signature
        },
    },
    methods: {
        isValidMatrixId(id) {
            return /^@[^:]+:.+$/.test(id)
        },
        addSigner() {
            this.signers.push({ matrixId: '' })
        },
        removeSigner(index) {
            this.signers.splice(index, 1)
        },
        close() {
            this.show = false
            this.$emit('close')
        },
        formatDate(timestamp) {
            return new Date(timestamp).toLocaleString()
        },
        async createSession() {
            this.loading = true
            try {
                const validSigners = this.signers
                    .filter(s => this.isValidMatrixId(s.matrixId))
                    .map(s => s.matrixId)

                const response = await axios.post(
                    generateUrl('/apps/docuseal_integration/api/matrix/sessions'),
                    {
                        file_path: this.filePath,
                        signers: validSigners,
                    }
                )

                this.session = response.data
                this.step = 'instructions'
                showSuccess(t('docuseal_integration', 'Signing room created'))

            } catch (error) {
                console.error('Failed to create session:', error)
                showError(t('docuseal_integration', 'Failed to create signing session'))
            } finally {
                this.loading = false
            }
        },
        async submitSignature() {
            this.submitting = true
            try {
                // Detect key type
                let keyType = 'ssh-ed25519'
                if (this.publicKey.includes('BEGIN PGP')) {
                    keyType = 'gpg'
                } else if (this.publicKey.startsWith('ssh-rsa')) {
                    keyType = 'ssh-rsa'
                } else if (this.publicKey.startsWith('ecdsa')) {
                    keyType = 'ecdsa-sha2-nistp256'
                }

                const response = await axios.post(
                    generateUrl(`/apps/docuseal_integration/api/matrix/sessions/${this.session.session_id}/sign`),
                    {
                        signature: this.signature,
                        public_key: this.publicKey,
                        key_type: keyType,
                        matrix_id: this.myMatrixId,
                    }
                )

                showSuccess(t('docuseal_integration', 'Signature submitted'))
                await this.refreshStatus()
                this.step = 'status'

            } catch (error) {
                console.error('Failed to submit signature:', error)
                const errorMsg = error.response?.data?.error || 'Unknown error'
                showError(t('docuseal_integration', 'Failed to submit signature: {error}', { error: errorMsg }))
            } finally {
                this.submitting = false
            }
        },
        async refreshStatus() {
            try {
                const response = await axios.get(
                    generateUrl(`/apps/docuseal_integration/api/matrix/sessions/${this.session.session_id}`)
                )

                this.session = response.data.session
                this.signatures = response.data.signatures
                this.missingSigners = response.data.missing_signers
                this.allSigned = response.data.all_signed

            } catch (error) {
                console.error('Failed to fetch status:', error)
            }
        },
    },
}
</script>

<style scoped lang="scss">
.matrix-signing-dialog {
    padding: 20px;
    min-width: 500px;
    max-width: 700px;

    h2 {
        margin-top: 0;
        margin-bottom: 20px;
    }

    .file-info {
        background: var(--color-background-dark);
        padding: 12px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group {
        margin-bottom: 16px;

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 6px;
        }
    }

    .signer-row {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
        align-items: flex-end;
    }

    .info-box {
        background: var(--color-primary-element-light);
        padding: 16px;
        border-radius: var(--border-radius);
        margin: 20px 0;

        h4 {
            margin-top: 0;
        }

        ol {
            margin-bottom: 0;
            padding-left: 20px;
        }
    }

    .success-box {
        background: var(--color-success);
        color: white;
        padding: 12px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .session-info {
        background: var(--color-background-dark);
        padding: 16px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;

        p {
            margin: 8px 0;
        }
    }

    .hash {
        display: block;
        font-family: monospace;
        font-size: 12px;
        word-break: break-all;
        background: var(--color-background-darker);
        padding: 8px;
        border-radius: 4px;
    }

    .instructions-box {
        border: 1px solid var(--color-border);
        border-radius: var(--border-radius);
        margin-bottom: 20px;

        h3 {
            margin: 0;
            padding: 12px 16px;
            background: var(--color-background-dark);
            border-bottom: 1px solid var(--color-border);
        }

        .instruction-tabs {
            display: flex;
            border-bottom: 1px solid var(--color-border);

            button {
                flex: 1;
                padding: 10px;
                border: none;
                background: none;
                cursor: pointer;

                &.active {
                    background: var(--color-primary-element-light);
                    font-weight: bold;
                }
            }
        }

        .instruction-content {
            padding: 16px;

            pre {
                background: var(--color-background-darker);
                padding: 12px;
                border-radius: 4px;
                overflow-x: auto;

                code {
                    font-size: 12px;
                }
            }
        }
    }

    .signatures-list {
        margin-bottom: 20px;

        h3 {
            margin-bottom: 12px;
        }

        .signature-item {
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            padding: 12px;
            margin-bottom: 8px;

            &.verified {
                border-left: 4px solid var(--color-success);
            }

            &.invalid {
                border-left: 4px solid var(--color-error);
            }

            .sig-header {
                display: flex;
                justify-content: space-between;
                margin-bottom: 4px;

                .signer {
                    font-weight: bold;
                }
            }

            .sig-details {
                font-size: 12px;
                color: var(--color-text-lighter);
                display: flex;
                gap: 16px;
            }
        }

        .missing-signers {
            background: var(--color-warning-hover);
            padding: 12px;
            border-radius: var(--border-radius);

            h4 {
                margin: 0 0 8px 0;
            }

            ul {
                margin: 0;
                padding-left: 20px;
            }
        }
    }

    .complete-box {
        background: var(--color-success);
        color: white;
        padding: 16px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        text-align: center;

        strong {
            display: block;
            font-size: 18px;
            margin-bottom: 8px;
        }

        p {
            margin: 0;
            opacity: 0.9;
        }
    }

    .dialog-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid var(--color-border);
    }
}
</style>
