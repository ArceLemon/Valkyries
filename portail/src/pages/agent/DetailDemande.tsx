import { ArrowLeft, CheckCircle, Circle, Clock, MinusCircle, XCircle } from '@phosphor-icons/react'
import type { Icon } from '@phosphor-icons/react'
import { Link, useParams } from 'react-router-dom'
import { BlocPreuve } from '../../components/BlocPreuve'
import { BoutonCopier } from '../../components/BoutonCopier'
import { CodeSms } from '../../components/CodeSms'
import { Ressource } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Dl, Panneau } from '../../components/Panneau'
import { BadgeStatut } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { cx } from '../../lib/cx'
import { dateHeure, estEnCours, explicationMotif, libelleRegistre, validiteDeLAttestation } from '../../lib/format'
import type { Demande } from '../../lib/types'
import { useApi } from '../../lib/useApi'

type EtatEtape = 'fait' | 'en_cours' | 'echec' | 'a_venir' | 'ignore'

interface Etape {
  libelle: string
  etat: EtatEtape
  detail?: string
}

/** Reconstitue le déroulement d'une demande à partir de son état. */
function etapes(d: Demande): Etape[] {
  const consentement = d.consentement !== null
  const s = d.statut
  const refusOuSansReponse = s === 'refusee_citoyen' || (s === 'expiree' && d.motif === 'consentement_non_donne')

  const liste: Etape[] = [
    { libelle: 'Demande reçue', etat: 'fait' },
    { libelle: 'Droits de votre organisme vérifiés', etat: 'fait' },
  ]

  if (consentement) {
    liste.push({
      libelle: 'Accord du citoyen',
      etat: s === 'en_attente_consentement' ? 'en_cours' : refusOuSansReponse ? 'echec' : 'fait',
      detail:
        s === 'refusee_citoyen'
          ? 'Le citoyen a refusé.'
          : refusOuSansReponse
            ? "Le citoyen n'a pas répondu à temps."
            : s === 'en_attente_consentement'
              ? d.motif === 'citoyen_sans_compte'
                ? "Le citoyen n'a pas de compte actif."
                : 'En attente de sa décision.'
              : undefined,
    })
  }

  const registreEtat: EtatEtape =
    refusOuSansReponse || s === 'en_attente_consentement'
      ? s === 'en_attente_consentement'
        ? 'a_venir'
        : 'ignore'
      : s === 'en_attente_registre' || s === 'en_cours' || s === 'recue'
        ? 'en_cours'
        : s === 'expiree'
          ? 'echec'
          : 'fait'
  liste.push({
    libelle: 'Registre interrogé',
    etat: registreEtat,
    detail:
      s === 'en_attente_registre'
        ? 'Le registre ne répond pas : nouvelle tentative automatique.'
        : s === 'expiree' && d.motif === 'registre_indisponible'
          ? 'Le registre est resté indisponible.'
          : undefined,
  })

  liste.push({
    libelle: 'Réponse calculée',
    etat: s === 'terminee' ? 'fait' : s === 'ambigue' ? 'echec' : registreEtat === 'ignore' ? 'ignore' : 'a_venir',
    detail: s === 'ambigue' ? 'Plusieurs personnes correspondent.' : undefined,
  })

  return liste
}

const ICONES: Record<EtatEtape, { icone: Icon; classes: string; texte: string }> = {
  fait: { icone: CheckCircle, classes: 'text-accent', texte: 'Terminé' },
  en_cours: { icone: Clock, classes: 'text-warn-soft-text', texte: 'En cours' },
  echec: { icone: XCircle, classes: 'text-danger-soft-text', texte: 'Échec' },
  a_venir: { icone: Circle, classes: 'text-muted', texte: 'À venir' },
  ignore: { icone: MinusCircle, classes: 'text-muted', texte: 'Non effectuée' },
}

function titreReponse(d: Demande): { texte: string; classes: string } {
  if (d.reponse === 'oui') return { texte: 'Oui', classes: 'text-accent' }
  if (d.reponse === 'non') return { texte: 'Non', classes: 'text-text' }
  if (d.reponse === 'impossible') return { texte: 'Impossible', classes: 'text-warn-soft-text' }
  if (d.statut === 'ambigue') return { texte: 'Ambiguë', classes: 'text-warn-soft-text' }
  return { texte: 'En attente', classes: 'text-muted' }
}

export function DetailDemande() {
  const { reference = '' } = useParams()
  const etat = useApi<Demande>(`/demandes/${encodeURIComponent(reference)}`, (d) =>
    d && estEnCours(d.statut) ? 5000 : null,
  )

  return (
    <>
      <Link to="/demandes" className="mb-4 inline-flex min-h-9 items-center gap-2 text-sm font-medium text-accent hover:underline">
        <ArrowLeft size={18} aria-hidden="true" />
        Mes demandes
      </Link>

      <Ressource etat={etat} lignes={4}>
        {(d) => {
          const titre = titreReponse(d)
          const explication = explicationMotif(d.statut, d.motif)
          const pas = etapes(d)
          return (
            <>
              <PageHeader
                titre={d.parametre ? `${d.libelle} (${d.parametre})` : d.libelle}
                description={d.finalite}
                actions={<BadgeStatut statut={d.statut} />}
              />

              <div className="grid gap-6 lg:grid-cols-[3fr_2fr]">
                <div className="space-y-6">
                  <Panneau titre="Réponse">
                    <p className={cx('text-5xl font-semibold', titre.classes)} aria-live="polite">
                      {titre.texte}
                    </p>
                    {explication && (
                      <Notice
                        ton={d.statut === 'en_attente_registre' || d.statut === 'ambigue' || d.reponse === 'impossible' ? 'attention' : 'info'}
                        className="mt-5"
                      >
                        {explication}
                      </Notice>
                    )}
                    {d.reponse === 'oui' && (
                      <p className="mt-4 text-base text-muted">
                        Vous recevez seulement cette confirmation. Aucune donnée du registre ne vous a été transmise.
                      </p>
                    )}
                  </Panneau>

                  {d.code_attendu && <CodeSms demande={d} onAccepte={etat.recharger} />}

                  {d.attestation ? (
                    <Panneau titre="Attestation signée" description="Une preuve que n'importe qui peut vérifier, sans compte.">
                      <BlocPreuve jeton={d.attestation} valideJusqua={validiteDeLAttestation(d.attestation)} />
                    </Panneau>
                  ) : (
                    d.reponse === 'non' && (
                      <Notice ton="info">
                        Aucune attestation n'est émise pour une réponse « non » : il n'y a rien à prouver.
                      </Notice>
                    )
                  )}
                </div>

                <div className="space-y-6">
                  <Panneau titre="Où en est la demande">
                    <ol className="space-y-4">
                      {pas.map((e) => {
                        const { icone: Icone, classes, texte } = ICONES[e.etat]
                        return (
                          <li key={e.libelle} className="flex gap-3">
                            <Icone size={24} className={cx('mt-0.5 shrink-0', classes)} aria-hidden="true" />
                            <div>
                              <p className="text-base font-medium text-text">
                                {e.libelle} <span className="sr-only">: {texte}</span>
                              </p>
                              {e.detail && <p className="text-sm text-muted">{e.detail}</p>}
                            </div>
                          </li>
                        )
                      })}
                    </ol>
                  </Panneau>

                  <Panneau titre="Détails">
                    <Dl
                      elements={[
                        ['Source', libelleRegistre(d.source)],
                        ['Posée le', dateHeure(d.creee_le)],
                        ['Traitée le', dateHeure(d.traitee_le)],
                        [
                          'Référence',
                          <span key="r" className="flex flex-wrap items-center gap-2">
                            <span className="mono break-all">{d.reference}</span>
                            <BoutonCopier valeur={d.reference} />
                          </span>,
                        ],
                      ]}
                    />
                  </Panneau>
                </div>
              </div>
            </>
          )
        }}
      </Ressource>
    </>
  )
}
