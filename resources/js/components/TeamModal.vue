<script setup>
import { computed } from 'vue';
import { useLeagueStore } from '../stores/league';
import { t } from '../i18n';

const league = useLeagueStore();

const squad = computed(() => league.openSquad);

const statKeys = [
    ['pace', 'PAC', 'DIV'],
    ['shooting', 'SHO', 'HAN'],
    ['passing', 'PAS', 'KIC'],
    ['dribbling', 'DRI', 'POS'],
    ['defending', 'DEF', 'REF'],
    ['physical', 'PHY', 'PHY'],
];

function statLabel(player, outfieldLabel, keeperLabel) {
    return player.position === 'GK' ? keeperLabel : outfieldLabel;
}

function ratingTone(value) {
    if (value >= 85) return 'is-elite';
    if (value >= 78) return 'is-great';
    if (value >= 70) return 'is-good';
    return 'is-mid';
}

function formTone(average) {
    if (average >= 7.5) return 'is-elite';
    if (average >= 6.8) return 'is-great';
    return 'is-good';
}
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="league.openTeamId" class="modal" @click.self="league.closeTeam()">
                <div class="modal__card" role="dialog" aria-modal="true">
                    <button type="button" class="modal__close" aria-label="Close" @click="league.closeTeam()">&#10005;</button>

                    <div v-if="league.squadLoading || !squad" class="loading loading--modal">
                        <span class="loading__ball"></span>
                        <span class="loading__text">{{ t('loading.squad') }}</span>
                    </div>

                    <template v-else>
                        <header class="modal__head" :style="{ '--team-color': squad.team.color }">
                            <img v-if="squad.team.logo_url" class="modal__logo" :src="squad.team.logo_url" :alt="squad.team.name">
                            <div class="modal__title-wrap">
                                <h2 class="modal__title">{{ squad.team.name }}</h2>
                                <p class="modal__meta">
                                    {{ squad.team.country }} &middot; {{ t('team.pot', { n: squad.team.pot }) }} &middot; {{ t('team.players', { n: squad.players.length }) }}
                                </p>
                            </div>
                            <div class="modal__powers">
                                <div class="modal__power">
                                    <b>{{ squad.team.power }}</b><small>{{ t('team.power') }}</small>
                                </div>
                                <div class="modal__power">
                                    <b>{{ squad.team.goalkeeper_factor }}</b><small>{{ t('team.gk') }}</small>
                                </div>
                                <div class="modal__power">
                                    <b>{{ squad.team.supporter_strength }}</b><small>{{ t('team.fans') }}</small>
                                </div>
                            </div>
                        </header>

                        <p class="modal__source">{{ t('team.source') }}</p>

                        <div class="squad">
                            <article
                                v-for="(player, index) in squad.players"
                                :key="player.id"
                                class="player"
                                :style="{ '--delay': `${Math.min(index, 16) * 26}ms` }"
                            >
                                <span class="player__ovr" :class="ratingTone(player.overall)">{{ player.overall }}</span>
                                <img
                                    v-if="player.avatar_url"
                                    class="player__avatar"
                                    :src="player.avatar_url"
                                    :alt="player.name"
                                    loading="lazy"
                                >
                                <div class="player__id">
                                    <b class="player__name">{{ player.name }}</b>
                                    <span class="player__meta">
                                        <i class="player__pos">{{ player.position }}</i>
                                        <img
                                            v-if="player.nationality_image"
                                            class="player__flag"
                                            :src="player.nationality_image"
                                            :alt="player.nationality"
                                            :title="player.nationality"
                                            loading="lazy"
                                        >
                                        <small v-if="player.apps" class="player__season">
                                            {{ t('team.apps', { n: player.apps, g: player.goals, a: player.assists }) }}
                                            <template v-if="player.yellows || player.reds"> · {{ player.yellows }}🟨<template v-if="player.reds">{{ player.reds }}🟥</template></template>
                                        </small>
                                    </span>
                                </div>
                                <b
                                    v-if="player.avg_rating !== null && player.apps"
                                    class="player__form"
                                    :class="formTone(player.avg_rating)"
                                    :title="t('team.avgTitle', { n: player.apps })"
                                >{{ player.avg_rating.toFixed(1) }}</b>
                                <div class="player__stats">
                                    <span v-for="[key, label, gkLabel] in statKeys" :key="key" class="player__stat">
                                        <small>{{ statLabel(player, label, gkLabel) }}</small>
                                        <b>{{ player[key] ?? '–' }}</b>
                                    </span>
                                </div>
                            </article>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
