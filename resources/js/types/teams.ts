export type TeamRole = 'owner' | 'admin' | 'member';

export type Team = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole;
    roleLabel?: string;
    isCurrent?: boolean;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole;
    role_label: string;
    /** What they may do — their own list, or the role's if they have none. */
    permissions: string[];
    /** Whether that list was chosen for them rather than inherited. */
    has_custom_permissions: boolean;
};

/** One heading on the permissions dialog, from App\Enums\TeamPermission. */
export type PermissionGroup = {
    group: string;
    permissions: Array<{ value: string; label: string }>;
};

export type TeamInvitation = {
    code: string;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    teamName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    team: {
        name: string;
        slug: string;
    };
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};
