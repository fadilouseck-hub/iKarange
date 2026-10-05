-- ----------------------------------------------------------------------------
-- Expand user roles ENUM to support new profile types
-- ----------------------------------------------------------------------------
ALTER TABLE `users`
    MODIFY `role` ENUM('administrateur','gestionnaire','prestataire','entreprise','adherent')
    NOT NULL DEFAULT 'gestionnaire';
