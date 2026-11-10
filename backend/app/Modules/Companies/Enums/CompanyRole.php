<?php

namespace App\Modules\Companies\Enums;

/**
 * Role a user holds inside one company. Permissions are derived from the role only;
 * keep this the single place where role capabilities are defined.
 */
enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Professional = 'professional';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Proprietário',
            self::Admin => 'Administrador',
            self::Receptionist => 'Recepcionista',
            self::Professional => 'Profissional',
        };
    }

    /**
     * Capability flags exposed to the SPA so it can hide actions the user cannot perform.
     * Derived from the same methods the policies use; never a substitute for them.
     *
     * @return array<string, bool>
     */
    public function permissions(): array
    {
        return [
            'manage_company' => $this->canManageCompany(),
            'manage_members' => $this->canManageMembers(),
            'manage_customers' => $this->canManageCustomers(),
            'manage_catalog' => $this->canManageCatalog(),
            'manage_availability' => $this->canManageAvailability(),
            'manage_appointments' => $this->canManageAppointments(),
            'delete_records' => $this->canDeleteRecords(),
        ];
    }

    /** Edit company information and settings. */
    public function canManageCompany(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /** Create, edit and archive customers. Every member may view them. */
    public function canManageCustomers(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Receptionist], true);
    }

    /** Permanently delete records (only allowed when they have no operational history). */
    public function canDeleteRecords(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /** Create, edit, activate and deactivate professionals and services. Every member may view them. */
    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /** Book, confirm, reschedule, check in, complete, cancel and mark no-shows. Every member may view. */
    public function canManageAppointments(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Receptionist], true);
    }

    /** Configure business hours, working hours, exceptions and blocked periods. Every member may view. */
    public function canManageAvailability(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /** View and manage the team (memberships and roles). */
    public function canManageMembers(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Whether this role may change or remove a membership currently holding $target,
     * and (when given) assign $newRole to it. Only owners may touch or grant ownership.
     */
    public function canAdministrate(self $target, ?self $newRole = null): bool
    {
        if (! $this->canManageMembers()) {
            return false;
        }

        if ($this === self::Owner) {
            return true;
        }

        return $target !== self::Owner && $newRole !== self::Owner;
    }
}
