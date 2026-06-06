<script setup>
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();
</script>

<template>
    <footer class="controls">
        <button
            type="button"
            class="controls__btn controls__btn--primary"
            :disabled="league.seasonOver || !!league.busyAction"
            @click="league.playWeek()"
        >
            <span v-if="league.busyAction === 'week'" class="controls__spinner" aria-hidden="true"></span>
            <template v-else>
                {{ t('controls.playWeek', { n: league.nextWeek ?? '–' }) }} <span aria-hidden="true">&#9654;</span>
            </template>
        </button>

        <button
            type="button"
            class="controls__btn"
            :disabled="league.seasonOver || !!league.busyAction"
            @click="league.playAll()"
        >
            <span v-if="league.busyAction === 'all'" class="controls__spinner" aria-hidden="true"></span>
            <template v-else>{{ t('controls.playAll') }} <span aria-hidden="true">&#9654;&#9654;</span></template>
        </button>

        <span class="controls__spacer"></span>

        <button
            type="button"
            class="controls__btn controls__btn--danger"
            :disabled="!!league.busyAction"
            @click="league.resetLeague()"
        >
            {{ t('controls.reset') }}
        </button>
    </footer>
</template>
