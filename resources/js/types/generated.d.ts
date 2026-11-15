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
        export type AccountRowData = {
            id: number;
            officeId: number;
            officeName: string;
            name: string;
            type: App.Enums.AccountType;
            typeLabel: string;
            currency: string;
            openingBalanceCents: number;
            openingBalanceFormatted: string;
            openingDate: string;
            balanceCents: number;
            balanceFormatted: string;
            active: boolean;
            canEdit: boolean;
            openingLocked: boolean;
        };
        export type AccountStatementRowData = {
            id: number;
            entryDate: string;
            description: string;
            direction: App.Enums.Direction;
            inCents: number;
            inFormatted: string;
            outCents: number;
            outFormatted: string;
            runningCents: number;
            runningFormatted: string;
        };
        export type AttendanceEmployeeOptionData = {
            id: number;
            name: string;
            officeId: number;
            officeName: string;
        };
        export type AttendanceFiltersData = {
            q: string;
            employee: number | null;
            office: number | null;
            status: string;
            from: string | null;
            to: string | null;
            sort: string;
            direction: string;
            perPage: number;
        };
        export type AttendancePresenceData = {
            employeeId: number;
            name: string;
            officeName: string;
            state: string;
            stateLabel: string;
            since: string | null;
            sinceLabel: string;
            todayMinutes: number;
            todayFormatted: string;
        };
        export type AttendanceSessionData = {
            id: number;
            employeeId: number;
            employeeName: string;
            officeId: number;
            officeName: string;
            startedAt: string;
            endedAt: string | null;
            startedAtLocal: string;
            endedAtLocal: string | null;
            status: App.Enums.AttendanceStatus;
            statusLabel: string;
            reviewStatus: App.Enums.AttendanceReviewStatus;
            reviewLabel: string;
            source: App.Enums.AttendanceSource;
            sourceLabel: string;
            workedMinutes: number;
            workedFormatted: string;
            report: string | null;
            blockers: string | null;
            isOpen: boolean;
        };
        export type AttendanceSettingsData = {
            autoCloseTime: string;
            remindersEnabled: boolean;
            reminderAfterMinutes: number;
            awayLimitMinutes: number;
            paidBreakMinutes: number;
            selfCorrectionEnabled: boolean;
            selfCorrectionHours: number;
            over10hReminderEnabled: boolean;
            reportsChannelId: string | null;
            offices: App.Data.OfficeAttendanceChannelData[];
        };
        export type AttentionContractData = {
            id: number;
            clientId: number | null;
            clientName: string;
            officeName: string;
            amountCents: number;
            cycleLabel: string;
            startsOn: string;
            endsOn: string | null;
            reason: string;
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
            clientId: number | null;
            clientName: string;
            partyName: string;
            supplierId: number | null;
            categoryId: number | null;
            categoryName: string | null;
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
        export type CorrectionRequestData = {
            id: number;
            sessionId: number;
            employeeId: number;
            employeeName: string;
            officeName: string;
            startedAt: string;
            endedAt: string | null;
            proposedEndedAt: string;
            note: string | null;
            createdAt: string;
            status: App.Enums.CorrectionStatus;
            statusLabel: string;
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
        export type DuePostponementData = {
            oldDueOn: string;
            newDueOn: string;
            reason: string;
            userName: string;
            at: string | null;
        };
        export type DueRowData = {
            id: number;
            contractId: number | null;
            clientId: number | null;
            employeeId: number | null;
            partyName: string;
            partyType: string;
            officeId: number;
            officeName: string;
            description: string | null;
            kind: App.Enums.DueKind;
            kindLabel: string;
            direction: App.Enums.Direction;
            directionLabel: string;
            period: string | null;
            dueOn: string | null;
            amountDueCents: number;
            amountPaidCents: number;
            remainingCents: number;
            creditCents: number;
            status: App.Enums.DueStatus;
            statusLabel: string;
            isOverdue: boolean;
            canceledReason: string | null;
            postponements: App.Data.DuePostponementData[];
            abilities: {
                postpone: boolean;
                cancel: boolean;
                pay: boolean;
                remind: boolean;
                advance: boolean;
            };
        };
        export type EmployeeData = {
            id: number;
            name: string;
            phone: string | null;
            title: string | null;
            hireDate: string | null;
            status: App.Enums.EmployeeStatus;
            statusLabel: string;
            officeId: number;
            officeName: string;
            discordUserId: string | null;
            contractType: App.Enums.EmploymentType;
            contractTypeLabel: string;
            expectedMinutesPerDay: number;
            workDays: number[];
            scheduleStart: string | null;
            scheduleEnd: string | null;
            lateGraceMinutes: number;
            remindersEnabled: boolean;
            hasAttendanceCode: boolean;
            attendanceCodeSetAt: string | null;
        };
        export type EmployeeWithSalaryData = {
            employee: App.Data.EmployeeData;
            currentSalaryCents: number | null;
            currentSalaryFormatted: string | null;
            hourlyRateCents: number | null;
            hourlyRateFormatted: string | null;
            salaryHistory: App.Data.SalaryHistoryData[];
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
        export type MilestoneData = {
            id: number;
            position: number;
            label: string;
            amountCents: number;
            paidCents: number;
            dueOn: string | null;
            trigger: App.Enums.MilestoneTrigger;
            deliveredAt: string | null;
            requestedAt: string | null;
            dueStatus: App.Enums.DueStatus | null;
            waitingForDelivery: boolean;
            locked: boolean;
            canDeliver: boolean;
            canRequest: boolean;
        };
        export type OfficeAttendanceChannelData = {
            id: number;
            name: string;
            attendanceChannelId: string | null;
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
        export type PaymentAccountData = {
            id: number;
            officeId: number;
            officeName: string;
            name: string;
            typeLabel: string;
        };
        export type PaymentRowData = {
            id: number;
            dueId: number;
            dueLabel: string;
            amountCents: number;
            amountFormatted: string;
            paidOn: string;
            methodLabel: string;
            reference: string | null;
            accountName: string;
            recordedBy: string | null;
            hasReceipt: boolean;
            isReversed: boolean;
            reversalReason: string | null;
            canReverse: boolean;
        };
        export type PayrollLineData = {
            employeeId: number;
            employeeName: string;
            officeName: string;
            salaryCents: number;
            bonusCents: number;
            deductionCents: number;
            advanceCents: number;
            netCents: number;
            state: string;
            dueId: number | null;
            note: string | null;
        };
        export type SalaryHistoryData = {
            id: number;
            amountCents: number;
            amountFormatted: string;
            effectiveFrom: string;
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
            milestonePaymentTermDays: number;
            weekStartOptions: Record<string, string>;
        };
        export type TriageFiltersData = {
            tab: string;
            direction: string;
            kind: string;
            client: number | null;
            office: number | null;
            assignee: number | null;
        };
        export type TriageTabData = {
            value: string;
            label: string;
            count: number;
            collectCents: number | null;
            payCents: number | null;
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
        export type WorkDashboardData = {
            name: string;
            state: string;
            serverNow: string;
            sessionStartedAt: string | null;
            breakStartedAt: string | null;
            todayWorkedMinutes: number;
            today: App.Data.WorkHoursPeriodData;
            week: App.Data.WorkHoursPeriodData;
            month: App.Data.WorkHoursPeriodData;
            recent: App.Data.WorkSessionData[];
            correction: {
                id: number;
                label: string;
                endTime: string;
            } | null;
            reportPrompt: {
                id: number;
                label: string;
            } | null;
        };
        export type WorkHoursPeriodData = {
            expectedMinutes: number;
            workedMinutes: number;
            remainingMinutes: number;
            extraMinutes: number;
            behindBeforeTodayMinutes: number;
            expectedFormatted: string;
            workedFormatted: string;
            remainingFormatted: string;
            extraFormatted: string;
            behindBeforeTodayFormatted: string;
        };
        export type WorkSessionData = {
            id: number;
            dateLabel: string;
            startLabel: string;
            endLabel: string | null;
            workedMinutes: number;
            workedFormatted: string;
            autoClosed: boolean;
            report: string | null;
            blockers: string | null;
        };
    }
    namespace Enums {
        export type AccountType = "cash" | "ccp" | "bank" | "other";
        export type AdjustmentType = "advance" | "bonus" | "deduction";
        export type AttendanceReviewStatus = "ok" | "needs_review" | "approved";
        export type AttendanceSource = "discord" | "web" | "admin";
        export type AttendanceStatus = "open" | "closed" | "auto_closed";
        export type CategoryKind = "cost" | "income";
        export type ContractStatus = "active" | "paused" | "canceled";
        export type ContractType =
            "saas_renewal" | "custom_project" | "recurring_cost";
        export type CorrectionStatus = "pending" | "approved" | "rejected";
        export type Cycle = "monthly" | "quarterly" | "yearly" | "once";
        export type Direction = "in" | "out";
        export type DueKind =
            "saas" | "project" | "salary" | "expense" | "other";
        export type DueStatus = "pending" | "partial" | "paid" | "canceled";
        export type EmployeeStatus = "active" | "inactive";
        export type EmploymentType =
            "fixed_monthly" | "hourly" | "part_time" | "freelancer";
        export type LeaveStatus = "pending" | "approved" | "rejected";
        export type LeaveType = "paid" | "unpaid" | "sick";
        export type MilestoneTrigger = "date" | "delivery";
        export type PaymentMethod =
            "cash" | "ccp" | "baridimob" | "bank_transfer" | "other";
        export type Role = "owner" | "finance" | "account_manager";
    }
}
