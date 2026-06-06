<script setup>
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();
</script>

<template>
    <nav class="group-tabs" aria-label="Views">
        <button
            v-for="(group, index) in league.groups"
            :key="group.id"
            type="button"
            class="group-tabs__tab"
            :class="{
                'is-active': league.view === 'groups' && group.name === league.selectedGroup,
                'is-revealing': league.revealStep >= 0,
            }"
            :style="{ '--reveal-delay': `${index * 60}ms` }"
            @click="league.focusGroup(group.name)"
        >
            <small>{{ t('tabs.group') }}</small>{{ group.name }}
        </button>

        <span class="group-tabs__divider" aria-hidden="true"></span>

        <button
            type="button"
            class="group-tabs__tab group-tabs__tab--wide"
            :class="{ 'is-active': league.view === 'knockout', 'is-hot': league.knockout?.available && !league.knockout?.champion }"
            @click="league.focusView('knockout')"
        >
            <small>{{ t('tabs.phase') }}</small>{{ t('tabs.bracket') }}
        </button>

        <button
            type="button"
            class="group-tabs__tab group-tabs__tab--wide"
            :class="{ 'is-active': league.view === 'stats' }"
            @click="league.focusView('stats')"
        >
            <small>{{ t('tabs.data') }}</small>{{ t('tabs.stats') }}
        </button>
    </nav>
</template>
