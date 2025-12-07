<template>
    <NcModal
        :show="show"
        :title="t('docuseal_integration', 'Send for Signing')"
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

            <!-- User Selection (like sharing) -->
            <div class="form-group">
                <label>{{ t('docuseal_integration', 'Select signers') }}</label>
                <NcSelect
                    v-model="selectedUsers"
                    :options="userSearchResults"
                    :loading="searchingUsers"
                    :filterable="false"
                    :placeholder="t('docuseal_integration', 'Search users...')"
                    :multiple="true"
                    label="displayName"
                    track-by="uid"
                    @search="searchUsers">
                    <template #option="{ option }">
                        <NcAvatar :user="option.uid" :size="24" />
                        <span class="user-option">
                            <span class="user-name">{{ option.displayName }}</span>
                            <span class="user-id">{{ option.uid }}</span>
                        </span>
                    </template>
                    <template #selected-option="{ option }">
                        <NcAvatar :user="option.uid" :size="20" />
                        <span>{{ option.displayName }}</span>
                    </template>
                </NcSelect>
                <p class="hint" v-if="selectedUsers.length === 0">
                    {{ t('docuseal_integration', 'Start typing to search for users') }}
                </p>
            </div>

            <!-- Message -->
            <div class="form-group">
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
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'

export default {
    name: 'DocuSealDialog',
    components: {
        NcModal,
        NcButton,
        NcTextArea,
        NcSelect,
        NcLoadingIcon,
        NcAvatar,
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
            selectedUsers: [],
            userSearchResults: [],
            searchingUsers: false,
            searchTimeout: null,
            message: '',
        }
    },
    computed: {
        isValid() {
            return this.selectedUsers.length > 0
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
        async searchUsers(query) {
            if (!query || query.length < 2) {
                this.userSearchResults = []
                return
            }

            // Debounce search
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout)
            }

            this.searchTimeout = setTimeout(async () => {
                this.searchingUsers = true
                try {
                    // Use Nextcloud's sharee search API (same as sharing dialog)
                    const response = await axios.get(
                        generateOcsUrl('apps/files_sharing/api/v1/sharees'),
                        {
                            params: {
                                search: query,
                                itemType: 'file',
                                perPage: 10,
                            },
                        }
                    )

                    const users = response.data?.ocs?.data?.users || []
                    this.userSearchResults = users.map(user => ({
                        uid: user.value.shareWith,
                        displayName: user.label,
                        email: user.value.shareWith, // Will resolve on backend
                    }))
                } catch (error) {
                    console.error('Failed to search users:', error)
                    this.userSearchResults = []
                } finally {
                    this.searchingUsers = false
                }
            }, 300)
        },
        close() {
            this.show = false
            this.$emit('close')
        },
        async submit() {
            if (!this.isValid) return

            this.loading = true
            try {
                // Send user IDs - backend will resolve to emails
                const submitters = this.selectedUsers.map(user => ({
                    uid: user.uid,
                    name: user.displayName,
                }))

                const response = await axios.post(
                    generateUrl('/apps/docuseal_integration/api/send-file'),
                    {
                        file_path: this.filePath,
                        template_id: this.selectedTemplate?.id,
                        submitters: submitters,
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

    .user-option {
        display: flex;
        flex-direction: column;
        margin-left: 8px;

        .user-name {
            font-weight: 500;
        }

        .user-id {
            font-size: 0.85em;
            color: var(--color-text-maxcontrast);
        }
    }

    .hint {
        font-size: 0.85em;
        color: var(--color-text-maxcontrast);
        margin-top: 4px;
    }
}
</style>
