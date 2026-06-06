<script setup>
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();
</script>

<template>
    <section class="draw" aria-label="Group stage draw">
        <div class="draw__intro reveal" style="--delay: 0ms">
            <h2 class="draw__title">{{ t('draw.title') }}</h2>
            <p class="draw__text">{{ t('draw.text') }}</p>
            <button
                type="button"
                class="controls__btn controls__btn--primary draw__button"
                :disabled="!!league.busyAction"
                @click="league.drawGroups()"
            >
                <span v-if="league.busyAction === 'draw'" class="controls__spinner" aria-hidden="true"></span>
                <template v-else>{{ t('draw.run') }} <span aria-hidden="true">&#9919;</span></template>
            </button>
        </div>

        <div class="draw__pots">
            <article
                v-for="(pot, index) in league.pots"
                :key="pot.pot"
                class="panel pot reveal"
                :style="{ '--delay': `${80 + index * 80}ms` }"
            >
                <div class="panel__head">
                    <h3 class="panel__title">{{ t('draw.pot', { n: pot.pot }) }}</h3>
                    <span class="panel__badge">{{ t('draw.clubs', { n: pot.teams.length }) }}</span>
                </div>
                <ul class="pot__list">
                    <li
                        v-for="team in pot.teams"
                        :key="team.id"
                        class="pot__team"
                        :style="{ '--team-color': team.color }"
                        @click="league.openTeam(team.id)"
                    >
                        <img v-if="team.logo_url" class="pot__logo" :src="team.logo_url" :alt="team.name" loading="lazy">
                        <i v-else class="table__chip" aria-hidden="true"></i>
                        <span class="pot__name">{{ team.name }}</span>
                        <span class="pot__country">{{ team.country }}</span>
                        <b class="pot__power">{{ team.power }}</b>
                    </li>
                </ul>
            </article>
        </div>
    </section>
</template>
