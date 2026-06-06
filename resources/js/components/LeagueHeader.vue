<script setup>
import { computed } from 'vue';
import { useLeagueStore } from '../stores/league';
import { t, i18n, setLocale } from '../i18n';

const league = useLeagueStore();

const segments = computed(() =>
    Array.from({ length: league.totalWeeks || 6 }, (_, i) => i + 1),
);
</script>

<template>
    <header class="header">
        <div class="header__brand">
            <span class="header__kicker">{{ t('header.kicker') }}</span>
            <h1 class="header__title">{{ t('header.title1') }}<em>{{ t('header.title2') }}</em></h1>
        </div>

        <div class="header__side">
            <div class="lang" role="group" aria-label="Language">
                <button
                    v-for="locale in ['en', 'tr']"
                    :key="locale"
                    type="button"
                    class="lang__btn"
                    :class="{ 'is-active': i18n.locale === locale }"
                    @click="setLocale(locale)"
                >{{ locale.toUpperCase() }}</button>
            </div>

            <div class="header__week">
                <template v-if="!league.drawn">
                    <span class="header__week-label">{{ t('header.stage') }}</span>
                    <span class="header__week-value header__week-value--draw">{{ t('header.draw') }}</span>
                </template>
                <template v-else-if="league.seasonOver">
                    <span class="header__week-label">{{ t('header.groupStage') }}</span>
                    <span class="header__week-value header__week-value--over">{{ t('header.ft') }}</span>
                </template>
                <template v-else>
                    <span class="header__week-label">{{ t('header.matchday') }}</span>
                    <span class="header__week-value">
                        {{ league.currentWeek }}<small>/{{ league.totalWeeks }}</small>
                    </span>
                </template>
                <div v-if="league.drawn" class="header__progress" role="presentation">
                    <span
                        v-for="w in segments"
                        :key="w"
                        class="header__segment"
                        :class="{ 'is-filled': w <= league.currentWeek }"
                    ></span>
                </div>
            </div>
        </div>
    </header>
</template>
