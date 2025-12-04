<template>
    <NcModal
        :show="show"
        :title="t('docuseal_integration', 'Send to DocuSeal')"
        @close="close">
        <div class="docuseal-dialog">
            <h2>{{ t('docuseal_integration', 'Send document for signing') }}</h2>
            <p class="file-info">
                <span class="icon-file"></span>
                {{ fileName }}
            </p>

            <!-- Template Selection -->
            <div class="form-group" v-if="templates.length > 0">
                <label for="template-select">
                    {{ t('docuseal_integration', 'Use template (optional)') }}
                </label>
                <NcSelect
                    id="template-select"
                    v-model="selectedTemplate"
                    :options="templateOptions"
                    :placeholder="t('docuseal_integration', 'Select a template...')"
                    label="name"
                    track-by="id"
                />
            </div>

            <!-- Recipients -->
            <div class="form-group">
                <label>{{ t('docuseal_integration', 'Recipients') }}</label>
                <div
                    v-for="(recipient, index) in recipients"
                    :key="index"
                    class="recipient-row">
                    <NcTextField
                        :value.sync="recipient.name"
                        :label="t('docuseal_integration', 'Name')"
                        :placeholder="t('docuseal_integration', 'John Doe')"
                    />
                    <NcTextField
                        :value.sync="recipient.email"
                        :label="t('docuseal_integration', 'Email')"
                        :placeholder="t('docuseal_integration', 'john@example.com')"
                        type="email"
                    />
                    <NcButton
                        v-if="recipients.length > 1"
                        type="tertiary"
                        @click="removeRecipient(index)">
                        <template #icon>
                            <span class="icon-delete"></span>
                        </template>
                    </NcButton>
                </div>
                <NcButton
                    type="secondary"
                    @click="addRecipient">
                    <template #icon>
                        <span class="icon-add"></span>
                    </template>
                    {{ t('docuseal_integration', 'Add recipient') }}
                </NcButton>
            </div>

            <!-- Options -->
            <div class="form-group">
                <NcCheckboxRadioSwitch
                    :checked.sync="sendEmail"
                    type="checkbox">
                    {{ t('docuseal_integration', 'Send email notification to recipients') }}
                </NcCheckboxRadioSwitch>
            </div>

            <!-- Message -->
            <div class="form-group" v-if="sendEmail">
                <label for="message">
                    {{ t('docuseal_integration', 'Message (optional)') }}
                </label>
                <NcTextArea
                    id="message"
                    v-model="message"
                    :placeholder="t('docuseal_integration', 'Please sign this document...')"
                    rows="3"
                />
            </div>

            <!-- Actions -->
            <div class="dialog-actions">
                <NcButton type="secondary" @click="close">
                    {{ t('docuseal_integration', 'Cancel') }}
                </NcButton>
                <NcButton
                    type="primary"
                    :disabled="!isValid || loading"
                    @click="submit">
                    <template #icon>
                        <NcLoadingIcon v-if="loading" :size="20" />
                    </template>
                    {{ t('docuseal_integration', 'Send for signing') }}
                </NcButton>
            </div>
        </div>
    </NcModal>
</template>

<script>
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.js'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
    name: 'DocuSealDialog',
    components: {
        NcModal,
        NcButton,
        NcTextField,
        NcTextArea,
        NcSelect,
        NcCheckboxRadioSwitch,
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
            loading: false,
            templates: [],
            selectedTemplate: null,
            recipients: [{ name: '', email: '' }],
            sendEmail: true,
            message: '',
        }
    },
    computed: {
        isValid() {
            return this.recipients.some(r => r.email && this.isValidEmail(r.email))
        },
        templateOptions() {
            return this.templates.map(t => ({
                id: t.id,
                name: t.name,
            }))
        },
    },
    async mounted() {
        await this.loadTemplates()
    },
    methods: {
        async loadTemplates() {
            try {
                const response = await axios.get(
                    generateUrl('/apps/docuseal_integration/api/templates')
                )
                this.templates = response.data || []
            } catch (error) {
                console.error('Failed to load templates:', error)
            }
        },
        addRecipient() {
            this.recipients.push({ name: '', email: '' })
        },
        removeRecipient(index) {
            this.recipients.splice(index, 1)
        },
        isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
        },
        close() {
            this.show = false
            this.$emit('close')
        },
        async submit() {
            if (!this.isValid) return

            this.loading = true
            try {
                const validRecipients = this.recipients.filter(r => r.email)

                const response = await axios.post(
                    generateUrl('/apps/docuseal_integration/api/send-file'),
                    {
                        file_path: this.filePath,
                        template_id: this.selectedTemplate?.id,
                        submitters: validRecipients,
                        send_email: this.sendEmail,
                        message: this.message,
                    }
                )

                this.$emit('success', response.data)
                this.close()
            } catch (error) {
                console.error('Failed to send document:', error)
                this.$emit('error', error.response?.data || error)
            } finally {
                this.loading = false
            }
        },
    },
}
</script>

<style scoped lang="scss">
.docuseal-dialog {
    padding: 20px;
    min-width: 400px;

    h2 {
        margin-top: 0;
    }

    .file-info {
        background: var(--color-background-dark);
        padding: 10px;
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
            margin-bottom: 4px;
        }
    }

    .recipient-row {
        display: flex;
        gap: 8px;
        margin-bottom: 8px;
        align-items: flex-end;
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
