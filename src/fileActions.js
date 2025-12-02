/**
 * File actions integration for DocuSeal
 * Adds "Send to DocuSeal" option to PDF files in Nextcloud Files
 */

import { registerFileAction, FileAction } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError, showMessage } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/style.css'

import DocuSealDialog from './components/DocuSealDialog.vue'
import Vue from 'vue'

/**
 * Open DocuSeal dialog for a file
 */
async function openDocuSealDialog(file) {
    // Create a container for the dialog
    const container = document.createElement('div')
    document.body.appendChild(container)

    // Create Vue instance with the dialog
    const DialogComponent = Vue.extend(DocuSealDialog)
    const dialog = new DialogComponent({
        propsData: {
            filePath: file.path,
            fileName: file.basename,
        },
    })

    // Handle dialog events
    dialog.$on('close', () => {
        dialog.$destroy()
        container.remove()
    })

    dialog.$on('success', (result) => {
        showSuccess(t('docuseal_integration', 'Document sent for signing'))
        dialog.$destroy()
        container.remove()
    })

    dialog.$on('error', (error) => {
        showError(t('docuseal_integration', 'Failed to send document: {error}', { error: error.message }))
    })

    dialog.$mount(container)
}

/**
 * Register the file action
 */
const docuSealAction = new FileAction({
    id: 'docuseal-send',
    displayName: () => t('docuseal_integration', 'Send to DocuSeal'),
    iconSvgInline: () => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>`,
    order: 100,

    /**
     * Only show for PDF files
     */
    enabled: (nodes) => {
        if (nodes.length !== 1) {
            return false
        }
        const node = nodes[0]
        return node.mime === 'application/pdf'
    },

    /**
     * Execute the action
     */
    exec: async (node) => {
        await openDocuSealDialog(node)
        return null
    },
})

// Register the file action when the page loads
if (window.OCA?.Files) {
    registerFileAction(docuSealAction)
}

export { docuSealAction }
