<?php

namespace App\Kuma;

/**
 * Rôles de la plateforme. Un seul rôle par compte.
 */
enum Role: string
{
    case Citoyen = 'citoyen';
    case Agent = 'agent';
    case Responsable = 'responsable';
    case Gestionnaire = 'gestionnaire';
    case Administrateur = 'administrateur';
    case Auditeur = 'auditeur';
}
