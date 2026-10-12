const humanize = (value) => value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (letter) => letter.toUpperCase());

const createElement = (tag, className, text = null) => {
    const element = document.createElement(tag);
    if (className) {
        element.className = className;
    }
    if (text !== null) {
        element.textContent = text;
    }

    return element;
};

const createChip = (text, tone = 'neutral') => createElement(
    'span',
    `spatial-detail-chip spatial-detail-chip-${tone}`,
    text,
);

const createDetailRow = (label, value) => {
    const row = createElement('div', 'spatial-detail-row');
    row.append(createElement('dt', 'spatial-detail-key', label), createElement('dd', 'spatial-detail-value', value));

    return row;
};

const createField = (label, select) => {
    const wrapper = createElement('label', 'spatial-detail-field');
    wrapper.append(createElement('span', 'spatial-detail-key', label), select);

    return wrapper;
};

const createActionButton = (label, onClick) => {
    const button = createElement('button', 'spatial-detail-action', label);
    button.type = 'button';
    button.addEventListener('click', onClick);

    return button;
};

const createSelect = (options, value) => {
    const select = createElement('select', 'spatial-detail-select');
    options.forEach(({ value: optionValue, label }) => {
        const option = createElement('option', '', label);
        option.value = optionValue === null ? '' : String(optionValue);
        option.selected = option.value === (value === null ? '' : String(value));
        select.append(option);
    });

    return select;
};

const indexWorkspace = (workspace) => {
    const branches = new Map();
    const teams = new Map();

    workspace.branches.forEach((branch) => {
        branches.set(branch.id, branch);
        branch.teams.forEach((team) => teams.set(team.id, { ...team, branchId: branch.id, branchName: branch.name }));
    });

    return { branches, teams, users: new Map(workspace.users.map((user) => [user.id, user])) };
};

export const createSpatialPanel = ({ root, workspace, canEdit, updateUrl }) => {
    const panel = root.querySelector('[data-spatial-panel]');
    const panelName = root.querySelector('[data-spatial-panel-name]');
    const panelBody = root.querySelector('[data-spatial-panel-body]');
    const modeLabel = document.querySelector('[data-spatial-mode-label]');
    const editToggle = document.querySelector('[data-spatial-edit-toggle]');
    const viewToggle = document.querySelector('[data-spatial-view-toggle]');
    const pendingLabel = document.querySelector('[data-spatial-pending]');
    const undoButton = document.querySelector('[data-spatial-undo]');
    const cancelButton = document.querySelector('[data-spatial-cancel]');
    const saveButton = document.querySelector('[data-spatial-save]');
    const status = document.querySelector('[data-spatial-status]');
    const index = indexWorkspace(workspace);
    const pending = new Map();
    const history = [];
    let selectedId = null;
    let editMode = false;

    const currentAssignment = (user) => {
        const change = pending.get(user.id);

        return change ?? {
            teamId: user.teamIds?.[0] ?? null,
            reportsToId: user.reportsToId ?? null,
        };
    };

    const updateToolbar = () => {
        if (modeLabel) {
            modeLabel.textContent = editMode ? 'Mode edit' : 'Mode lihat';
        }
        if (editToggle) {
            editToggle.setAttribute('aria-pressed', editMode ? 'true' : 'false');
            editToggle.classList.toggle('is-active', editMode);
        }
        if (viewToggle) {
            viewToggle.setAttribute('aria-pressed', editMode ? 'false' : 'true');
            viewToggle.classList.toggle('is-active', ! editMode);
        }
        const hasPending = pending.size > 0;
        if (pendingLabel) {
            pendingLabel.classList.toggle('hidden', ! hasPending);
            pendingLabel.classList.toggle('flex', hasPending);
            pendingLabel.replaceChildren(
                Object.assign(document.createElement('i'), { className: 'size-1.5 rounded-full bg-orange' }),
                document.createTextNode(`${pending.size} perubahan belum disimpan`),
            );
        }
        undoButton?.classList.toggle('hidden', history.length === 0);
        cancelButton?.classList.toggle('hidden', ! hasPending);
        saveButton?.classList.toggle('hidden', ! hasPending);
    };

    const setStatus = (message, error = false) => {
        if (status) {
            status.textContent = message;
            status.classList.toggle('text-red-600', error);
        }
    };

    const renderEmpty = () => {
        panel?.classList.add('is-empty');
        panel?.setAttribute('aria-hidden', 'true');
        panelName.textContent = 'Scene organisasi';
        panelBody.replaceChildren(createElement('p', 'text-xs leading-5 text-muted', 'Pilih pion user untuk melihat detail, role, dan struktur pelaporannya.'));
    };

    const renderUser = (user) => {
        panel?.classList.remove('is-empty');
        panel?.setAttribute('aria-hidden', 'false');
        const assignment = currentAssignment(user);
        const team = index.teams.get(assignment.teamId);
        const branch = team ? index.branches.get(team.branchId) : index.branches.get(user.branchId);
        const reportsTo = index.users.get(assignment.reportsToId);
        const roles = [
            ...(user.globalRoles ?? []).map((role) => ({ name: role, tone: 'ink' })),
            ...(user.roles ?? []).map((role) => ({ name: role.name, tone: 'lime' })),
        ];
        const scope = (user.globalRoles ?? []).includes('super_admin')
            ? 'Semua cabang'
            : `Cabang: ${branch?.name ?? 'Belum ditentukan'}`;

        panelName.textContent = user.name;
        panelBody.replaceChildren();
        const identity = createElement('div', 'spatial-inspector-identity');
        identity.append(
            createElement('div', 'spatial-inspector-avatar', user.name.slice(0, 1).toUpperCase()),
            createElement('div', 'min-w-0 text-xs text-muted', user.email),
        );
        panelBody.append(identity);

        const moveCard = createElement('div', 'spatial-move-card');
        moveCard.append(createElement('p', 'text-[10px] font-semibold text-muted', 'Pindah tim'));
        moveCard.append(createElement('p', 'mt-1 text-xs font-semibold text-ink', `${team?.name ?? 'Belum ditentukan'} · ${branch?.name ?? 'Belum ditentukan'}`));
        panelBody.append(moveCard);

        const details = createElement('dl', 'spatial-detail-list');
        details.append(
            createDetailRow('Cabang', branch?.name ?? 'Belum ditentukan'),
            createDetailRow('Tim', team?.name ?? 'Belum ditentukan'),
            createDetailRow('Atasan', reportsTo?.name ?? 'Belum diatur'),
            createDetailRow('Scope akses', scope),
        );
        panelBody.append(details);

        const roleSection = createElement('div', 'spatial-detail-section');
        roleSection.append(createElement('p', 'spatial-detail-section-title', 'Role (read-only)'));
        const roleList = createElement('div', 'spatial-detail-chip-list');
        if (roles.length === 0) {
            roleList.append(createChip('Belum ada role'));
        } else {
            roles.forEach((role) => roleList.append(createChip(humanize(role.name), role.tone)));
        }
        roleSection.append(roleList);
        panelBody.append(roleSection);

        const accessSection = createElement('div', 'spatial-detail-section');
        accessSection.append(createElement('p', 'spatial-detail-section-title', 'Akses (read-only)'), createChip(scope));
        panelBody.append(accessSection);

        const focusSection = createElement('div', 'spatial-detail-actions');
        focusSection.append(createActionButton('Fokus pawn', () => {
            root.dispatchEvent(new CustomEvent('spatial:focus-user', { detail: { userId: user.id } }));
        }));
        if (team) {
            focusSection.append(createActionButton('Fokus tim', () => {
                root.dispatchEvent(new CustomEvent('spatial:focus-team', { detail: { teamId: team.id } }));
            }));
        }
        panelBody.append(focusSection);

        if (editMode && canEdit) {
            const editSection = createElement('div', 'spatial-detail-section');
            editSection.append(createElement('p', 'spatial-detail-section-title', 'Assignment'));
            const teamSelect = createSelect(
                [...index.teams.values()].map((optionTeam) => ({ value: optionTeam.id, label: `${optionTeam.name} · ${optionTeam.branchName}` })),
                assignment.teamId,
            );
            const reportsOptions = [...index.users.values()]
                .filter((optionUser) => optionUser.id !== user.id && optionUser.branchId === team?.branchId)
                .sort((left, right) => left.name.localeCompare(right.name))
                .map((optionUser) => ({ value: optionUser.id, label: optionUser.name }));
            reportsOptions.unshift({ value: null, label: 'Belum diatur' });
            const reportsSelect = createSelect(reportsOptions, assignment.reportsToId);
            teamSelect.addEventListener('change', () => {
                changeAssignment(user, { teamId: Number(teamSelect.value), reportsToId: assignment.reportsToId });
            });
            reportsSelect.addEventListener('change', () => {
                changeAssignment(user, { teamId: assignment.teamId, reportsToId: reportsSelect.value ? Number(reportsSelect.value) : null });
            });
            editSection.append(createField('Tim aktif', teamSelect), createField('Atasan', reportsSelect));
            panelBody.append(editSection);
        }
    };

    const render = () => {
        updateToolbar();
        root.classList.toggle('has-selection', selectedId !== null && index.users.has(selectedId));
        if (selectedId === null || ! index.users.has(selectedId)) {
            renderEmpty();
        } else {
            renderUser(index.users.get(selectedId));
        }
    };

    const changeAssignment = (user, nextAssignment) => {
        const previous = currentAssignment(user);
        history.push({ userId: user.id, assignment: previous });
        pending.set(user.id, nextAssignment);
        setStatus('Perubahan assignment siap disimpan.');
        render();
    };

    const stageAssignment = (userId, teamId) => {
        const user = index.users.get(userId);
        const team = index.teams.get(teamId);
        if (! user || ! team) {
            return false;
        }

        const current = currentAssignment(user);
        const reportsTo = index.users.get(current.reportsToId);
        const reportsToId = reportsTo?.branchId === team.branchId ? current.reportsToId : null;
        changeAssignment(user, { teamId, reportsToId });

        return true;
    };

    const selectUser = (userId) => {
        selectedId = userId === null ? null : Number(userId);
        render();
    };

    const toggleEditMode = () => {
        editMode = ! editMode;
        render();
    };

    const undo = () => {
        const previous = history.pop();
        if (! previous) {
            return;
        }
        const user = index.users.get(previous.userId);
        const original = { teamId: user.teamIds?.[0] ?? null, reportsToId: user.reportsToId ?? null };
        if (previous.assignment.teamId === original.teamId && previous.assignment.reportsToId === original.reportsToId) {
            pending.delete(previous.userId);
        } else {
            pending.set(previous.userId, previous.assignment);
        }
        render();
    };

    const cancel = () => {
        pending.clear();
        history.length = 0;
        setStatus('Perubahan dibatalkan.');
        render();
    };

    const save = async () => {
        if (pending.size === 0) {
            return;
        }
        saveButton?.setAttribute('disabled', 'disabled');
        setStatus('Menyimpan perubahan...');
        try {
            for (const [userId, assignment] of pending) {
                const response = await fetch(updateUrl.replace('__USER__', String(userId)), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ team_id: assignment.teamId, reports_to_id: assignment.reportsToId }),
                });
                if (! response.ok) {
                    throw new Error(`Gagal menyimpan assignment (${response.status}).`);
                }
                const user = index.users.get(userId);
                const previousTeamId = user.teamIds?.[0] ?? null;
                const previousTeam = index.teams.get(previousTeamId);
                const nextTeam = index.teams.get(assignment.teamId);
                user.teamIds = [assignment.teamId];
                user.reportsToId = assignment.reportsToId;
                user.branchId = nextTeam?.branchId ?? user.branchId;
                const previousUserIndex = previousTeam?.userIds?.indexOf(userId) ?? -1;
                if (previousTeam && previousUserIndex >= 0) {
                    previousTeam.userIds.splice(previousUserIndex, 1);
                }
                if (nextTeam && ! nextTeam.userIds.includes(userId)) {
                    nextTeam.userIds.push(userId);
                }
            }
            pending.clear();
            history.length = 0;
            setStatus('Perubahan berhasil disimpan.');
            render();
        } catch (error) {
            setStatus(error.message, true);
        } finally {
            saveButton?.removeAttribute('disabled');
            updateToolbar();
        }
    };

    editToggle?.addEventListener('click', toggleEditMode);
    viewToggle?.addEventListener('click', () => {
        if (editMode) {
            editMode = false;
            render();
        }
    });
    undoButton?.addEventListener('click', undo);
    cancelButton?.addEventListener('click', cancel);
    saveButton?.addEventListener('click', save);
    root.querySelector('[data-spatial-panel-close]')?.addEventListener('click', () => selectUser(null));
    render();

    return {
        selectUser,
        isEditMode: () => editMode,
        stageAssignment,
        render,
        dispose: () => {},
    };
};
