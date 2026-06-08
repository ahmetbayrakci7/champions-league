import { defineStore } from 'pinia';
import axios from 'axios';
import Swal from 'sweetalert2';
import { t } from '../i18n';

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    timer: 2600,
    timerProgressBar: true,
    showConfirmButton: false,
    background: '#0d1610',
    color: '#f2f7ee',
});

export const useLeagueStore = defineStore('league', {
    state: () => ({
        drawn: false,
        pots: [],
        groups: [],
        currentWeek: 0,
        totalWeeks: 0,
        seasonOver: false,
        knockout: null,
        selectedGroup: 'A',
        selectedWeek: 1,
        view: 'groups', // groups | knockout | stats
        loading: false,
        busyAction: null, // 'draw' | 'week' | 'all' | 'reset' | 'edit' | 'knockout'
        revealStep: -1,
        openTeamId: null,
        squads: {},
        squadLoading: false,
        openGameId: null,
        gameDetails: {},
        gameLoading: false,
        stats: null,
        statsLoading: false,
    }),

    getters: {
        group(state) {
            return state.groups.find((g) => g.name === state.selectedGroup) ?? null;
        },
        selectedWeekGames() {
            return this.group?.weeks.find((w) => Number(w.week) === Number(this.selectedWeek))?.games ?? [];
        },
        weeks() {
            return this.group?.weeks ?? [];
        },
        standings() {
            return this.group?.standings ?? [];
        },
        predictions() {
            return this.group?.predictions ?? null;
        },
        nextWeek(state) {
            return state.seasonOver ? null : state.currentWeek + 1;
        },
        predictionsUnlocked() {
            return this.predictions !== null;
        },
        rankedPredictions() {
            if (!this.predictions) return [];

            return this.standings
                .map((row) => ({ ...row, percent: this.predictions[row.team_id] ?? 0 }))
                .sort((a, b) => b.percent - a.percent);
        },
        openSquad(state) {
            return state.openTeamId ? state.squads[state.openTeamId] ?? null : null;
        },
        openGame(state) {
            return state.openGameId ? state.gameDetails[state.openGameId] ?? null : null;
        },
        knockoutNextLabel(state) {
            const next = state.knockout?.next;

            if (!next) return null;
            if (next.action === 'draw') return t('ko.draw_r16');
            if (next.action === 'done') return t('ko.done');

            return next.leg
                ? t('ko.play', { stage: t(`stage.${next.stage}`), leg: next.leg })
                : t('ko.playSingle', { stage: t(`stage.${next.stage}`) });
        },
    },

    actions: {
        applyState(data) {
            this.drawn = data.drawn;
            this.pots = data.pots;
            this.groups = data.groups;
            this.currentWeek = data.current_week;
            this.totalWeeks = data.total_weeks;
            this.seasonOver = data.season_over;
            this.knockout = data.knockout ?? null;

            // Results changed: cached details and leaderboards are stale.
            this.gameDetails = {};
            this.stats = null;

            if (!this.groups.find((g) => g.name === this.selectedGroup)) {
                this.selectedGroup = this.groups[0]?.name ?? 'A';
            }

            if (!this.drawn) {
                this.view = 'groups';
            }
        },

        focusWeek(week) {
            this.selectedWeek = Math.min(Math.max(week, 1), this.totalWeeks || 1);
        },

        focusGroup(name) {
            this.selectedGroup = name;
            this.view = 'groups';
        },

        focusView(view) {
            this.view = view;

            if (view === 'stats' && !this.stats) {
                this.fetchStats();
            }
        },

        async fetchState() {
            this.loading = true;
            try {
                const { data } = await axios.get('/api/league');
                this.applyState(data);
                this.focusWeek(this.currentWeek || 1);
            } catch (error) {
                this.notifyError(error);
            } finally {
                this.loading = false;
            }
        },

        async drawGroups() {
            await this.run('draw', async () => {
                const { data } = await axios.post('/api/league/draw');
                this.applyState(data);
                this.focusWeek(1);

                this.revealStep = 0;
                const timer = setInterval(() => {
                    this.revealStep += 1;
                    if (this.revealStep >= 40) {
                        clearInterval(timer);
                        this.revealStep = -1;
                    }
                }, 120);
            });
        },

        async playWeek() {
            await this.run('week', async () => {
                const { data } = await axios.post('/api/league/play-week');
                this.applyState(data);
                this.focusWeek(this.currentWeek);
            });
        },

        async playAll() {
            await this.run('all', async () => {
                const { data } = await axios.post('/api/league/play-all');
                this.applyState(data);
                this.focusWeek(this.totalWeeks);
            });
        },

        async advanceKnockout() {
            await this.run('knockout', async () => {
                const { data } = await axios.post('/api/knockout/advance');
                this.knockout = data;
                this.gameDetails = {};
                this.stats = null;

                if (data.champion) {
                    toast.fire({ icon: 'success', title: t('toast.champions', { name: data.champion.name }) });
                }
            });
        },

        async advanceKnockoutAll() {
            await this.run('knockout-all', async () => {
                const { data } = await axios.post('/api/knockout/advance-all');
                this.knockout = data;
                this.gameDetails = {};
                this.stats = null;

                if (data.champion) {
                    toast.fire({ icon: 'success', title: t('toast.champions', { name: data.champion.name }) });
                }
            });
        },

        async resetLeague() {
            const result = await Swal.fire({
                title: t('dialog.resetTitle'),
                text: t('dialog.resetText'),
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: t('dialog.resetConfirm'),
                cancelButtonText: t('dialog.resetCancel'),
                background: '#0d1610',
                color: '#f2f7ee',
                confirmButtonColor: '#ff5d5d',
                cancelButtonColor: '#1c2b1f',
            });

            if (!result.isConfirmed) return;

            await this.run('reset', async () => {
                const { data } = await axios.post('/api/league/reset');
                this.applyState(data);
                this.focusWeek(1);
                toast.fire({ icon: 'success', title: t('toast.reset') });
            });
        },

        async updateGame(gameId, homeGoals, awayGoals) {
            await this.run('edit', async () => {
                const { data } = await axios.put(`/api/games/${gameId}`, {
                    home_goals: homeGoals,
                    away_goals: awayGoals,
                });
                this.applyState(data);

                // Refresh the open match so its new summary loads in place.
                if (this.openGameId) {
                    await this.openMatch(this.openGameId);
                }

                toast.fire({ icon: 'success', title: t('toast.scoreUpdated') });
            });
        },

        async openTeam(teamId) {
            this.openTeamId = teamId;

            if (this.squads[teamId]) return;

            this.squadLoading = true;
            try {
                const { data } = await axios.get(`/api/teams/${teamId}`);
                this.squads[teamId] = data;
            } catch (error) {
                this.openTeamId = null;
                this.notifyError(error);
            } finally {
                this.squadLoading = false;
            }
        },

        closeTeam() {
            this.openTeamId = null;
        },

        async openMatch(gameId) {
            this.openGameId = gameId;

            if (this.gameDetails[gameId]) return;

            this.gameLoading = true;
            try {
                const { data } = await axios.get(`/api/games/${gameId}`);
                this.gameDetails[gameId] = data;
            } catch (error) {
                this.openGameId = null;
                this.notifyError(error);
            } finally {
                this.gameLoading = false;
            }
        },

        closeMatch() {
            this.openGameId = null;
        },

        async fetchStats() {
            this.statsLoading = true;
            try {
                const { data } = await axios.get('/api/stats');
                this.stats = data;
            } catch (error) {
                this.notifyError(error);
            } finally {
                this.statsLoading = false;
            }
        },

        async run(action, fn) {
            if (this.busyAction) return;
            this.busyAction = action;
            try {
                await fn();
            } catch (error) {
                this.notifyError(error);
            } finally {
                this.busyAction = null;
            }
        },

        notifyError(error) {
            const data = error?.response?.data;
            const title = data?.code
                ? t(`edit.${data.code}`)
                : (data?.message ?? t('toast.error'));

            toast.fire({ icon: 'error', title });
        },
    },
});
