declare namespace App {
    namespace Data {
        export type AccountOptionData = {
            id: number;
            officeId: number;
            officeName: string;
            name: string;
            type: string;
            balanceCents: number;
            balanceFormatted: string;
        };
        export type AuthData = {
            user: App.Data.UserData | null;
            role: App.Enums.Role | null;
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
        export type SettingsData = {
            appName: string;
            locale: string;
            displayTimezone: string;
            weekStartsOn: string;
            currency: string;
            currencySymbol: string;
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
    }
    namespace Enums {
        export type Role = "owner" | "finance" | "account_manager";
    }
}
