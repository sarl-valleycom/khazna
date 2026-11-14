declare namespace App {
    namespace Data {
        export type AccountManagerOptionData = {
            id: number;
            name: string;
            email: string;
            officeId: number | null;
            officeName: string | null;
            active: boolean;
        };
        export type AccountOptionData = {
            id: number;
            officeId: number;
            officeName: string;
            name: string;
            type: string;
            balanceCents: number;
            balanceFormatted: string;
        };
        export type AuditLogRowData = {
            id: number;
            event: string;
            description: string;
            subjectType: string | null;
            subjectId: number | null;
            causerName: string | null;
            causerId: number | null;
            createdAt: string;
            changes: Record<string, any>;
            reason: string | null;
        };
        export type AuthData = {
            user: App.Data.UserData | null;
            role: App.Enums.Role | null;
        };
        export type ClientFiltersData = {
            q: string;
            accountManager: number | null;
            office: number | null;
            sort: string;
            direction: string;
            perPage: number;
        };
        export type ClientImportPreviewData = {
            token: string;
            fileName: string;
            totalCount: number;
            validCount: number;
            invalidCount: number;
            duplicateCount: number;
            rows: App.Data.ClientImportRowData[];
        };
        export type ClientImportRowData = {
            line: number;
            status: string;
            name: string | null;
            phoneDisplay: string | null;
            managerEmail: string | null;
            reason: string | null;
        };
        export type ClientRowData = {
            id: number;
            name: string;
            contact: string | null;
            phone: string;
            phoneDisplay: string;
            email: string | null;
            address: string | null;
            notes: string | null;
            accountManagerId: number;
            accountManagerName: string;
            officeId: number | null;
            officeName: string | null;
            abilities: {
                view: boolean;
                update: boolean;
                delete: boolean;
            };
        };
        export type ClientShowData = {
            id: number;
            name: string;
            contact: string | null;
            phone: string;
            phoneDisplay: string;
            email: string | null;
            address: string | null;
            notes: string | null;
            accountManagerId: number;
            accountManagerName: string;
            accountManagerEmail: string;
            officeId: number | null;
            officeName: string | null;
            createdAt: string;
            abilities: {
                view: boolean;
                update: boolean;
                delete: boolean;
            };
        };
        export type ContractClientOptionData = {
            id: number;
            name: string;
            officeId: number | null;
            officeName: string | null;
        };
        export type ContractData = {
            id: number;
            clientId: number;
            clientName: string;
            officeId: number;
            officeName: string;
            type: App.Enums.ContractType;
            typeLabel: string;
            direction: App.Enums.Direction;
            directionLabel: string;
            amountCents: number;
            cycle: App.Enums.Cycle;
            cycleLabel: string;
            startsOn: string;
            endsOn: string | null;
            status: App.Enums.ContractStatus;
            statusLabel: string;
            autoRenew: boolean;
            notes: string | null;
            attachmentName: string | null;
            createdAt: string;
            abilities: {
                view: boolean;
                update: boolean;
                changeStatus: boolean;
                downloadAttachment: boolean;
            };
        };
        export type ContractFiltersData = {
            q: string;
            status: string;
            client: number | null;
            office: number | null;
            sort: string;
            direction: string;
            perPage: number;
        };
        export type DemoDueRowData = {
            id: number;
            client: string;
            clientArabic: string;
            phone: string;
            dueOn: string;
            status: string;
            direction: string;
            amountCents: number;
            amountFormatted: string;
        };
        export type DevUiData = {
            title: string;
            clientNameArabic: string;
            amountCents: number;
            amountFormatted: string;
            date: string;
            offices: App.Data.OfficeOptionData[];
            accounts: App.Data.AccountOptionData[];
            rows: App.Data.DemoDueRowData[];
            forecast: App.Data.ForecastPointData[];
            statuses: Record<string, string>;
            tabCounts: Record<string, number>;
            total: number;
            currentPage: number;
            perPage: number;
            sort: string | null;
            direction: string;
            search: string;
            statusFilter: string | null;
            activeTab: string;
            officeFilter: string | null;
            from: string | null;
            to: string | null;
        };
        export type FlashData = {
            success: string | null;
            error: string | null;
            info: string | null;
            warning: string | null;
            toast: {
                type: string;
                message: string;
            } | null;
        };
        export type ForecastPointData = {
            label: string;
            inflow: number;
            outflow: number;
            balance: number;
        };
        export type OfficeOptionData = {
            id: number;
            name: string;
            city: string;
        };
        export type OfficeRowData = {
            id: number;
            name: string;
            city: string;
            active: boolean;
            usersCount: number;
        };
        export type SettingsData = {
            appName: string;
            locale: string;
            displayTimezone: string;
            weekStartsOn: string;
            currency: string;
            currencySymbol: string;
        };
        export type SettingsPageData = {
            weekStart: string;
            reminderTemplate: string;
            currencyLabel: string;
            salaryDueDay: number;
            weekStartOptions: Record<string, string>;
        };
        export type UserData = {
            id: number;
            name: string;
            email: string;
            role: App.Enums.Role | null;
            officeId: number | null;
            active: boolean;
            avatar: string | null;
            emailVerifiedAt: string | null;
            createdAt: string | null;
            updatedAt: string | null;
            twoFactorEnabled: boolean;
            abilities: Record<string, boolean>;
        };
        export type UserRowData = {
            id: number;
            name: string;
            email: string;
            role: App.Enums.Role;
            roleLabel: string;
            officeId: number | null;
            officeName: string | null;
            active: boolean;
            isSelf: boolean;
        };
    }
    namespace Enums {
        export type AccountType = 'cash' | 'ccp' | 'bank' | 'other';
        export type ContractStatus = 'active' | 'paused' | 'canceled';
        export type ContractType =
            | 'saas_renewal'
            | 'custom_project'
            | 'recurring_cost';
        export type Cycle = 'monthly' | 'quarterly' | 'yearly' | 'once';
        export type Direction = 'in' | 'out';
        export type DueKind =
            | 'saas'
            | 'project'
            | 'salary'
            | 'expense'
            | 'other';
        export type DueStatus = 'pending' | 'partial' | 'paid' | 'canceled';
        export type PaymentMethod =
            | 'cash'
            | 'ccp'
            | 'baridimob'
            | 'bank_transfer'
            | 'other';
        export type Role = 'owner' | 'finance' | 'account_manager';
    }
}
