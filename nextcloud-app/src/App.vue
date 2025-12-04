<template>
    <NcContent app-name="docuseal_integration">
        <NcAppNavigation>
            <NcAppNavigationItem
                :name="t('docuseal_integration', 'Dashboard')"
                :to="{ name: 'dashboard' }"
                icon="icon-home">
            </NcAppNavigationItem>
            <NcAppNavigationItem
                :name="t('docuseal_integration', 'Submissions')"
                :to="{ name: 'submissions' }"
                icon="icon-files-dark">
            </NcAppNavigationItem>
            <NcAppNavigationItem
                :name="t('docuseal_integration', 'Templates')"
                :to="{ name: 'templates' }"
                icon="icon-template">
            </NcAppNavigationItem>
        </NcAppNavigation>
        <NcAppContent>
            <router-view />
        </NcAppContent>
    </NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'

export default {
    name: 'App',
    components: {
        NcContent,
        NcAppNavigation,
        NcAppNavigationItem,
        NcAppContent,
    },
}
</script>

<style scoped>
</style>
