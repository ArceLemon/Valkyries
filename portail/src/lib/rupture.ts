/** Explication, en français, de la cause d'une rupture détectée dans le journal. */
export const MOTIFS_RUPTURE: Record<string, string> = {
  chainage_rompu: 'une ligne a été supprimée ou la chaîne a été rompue',
  contenu_modifie: "le contenu d'une ligne a été modifié",
  point_de_controle_signature_invalide: 'un point de contrôle signé a été falsifié',
  point_de_controle_chainage_rompu: 'un point de contrôle a été supprimé',
  point_de_controle_ligne_absente: 'une ligne couverte par un point de contrôle a disparu',
  point_de_controle_incoherent: "l'historique a été réécrit après la signature d'un point de contrôle",
}
