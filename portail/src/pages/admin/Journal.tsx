import { PenNib, ShieldCheck } from '@phosphor-icons/react'
import { useMemo, useState } from 'react'
import { Bouton } from '../../components/Bouton'
import { Case, Select } from '../../components/Champs'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Panneau } from '../../components/Panneau'
import { PageHeader } from '../../components/PageHeader'
import { Segmente } from '../../components/Segmente'
import { TableJournal } from '../../components/TableJournal'
import { api, messageErreur } from '../../lib/api'
import { useAuth } from '../../lib/auth'
import { LIBELLE_ACTION, dateHeure } from '../../lib/format'
import { MOTIFS_RUPTURE } from '../../lib/rupture'
import type { LigneJournal, Liste, PointDeControle, QuestionCatalogue, VerificationJournal } from '../../lib/types'
import { useApi } from '../../lib/useApi'

type Onglet = 'journal' | 'integrite'

function Evenements() {
  const [action, setAction] = useState('')
  // Les connexions sont très nombreuses : on les masque par défaut pour voir l'essentiel.
  const [sansConnexions, setSansConnexions] = useState(true)
  const etat = useApi<Liste<LigneJournal>>(`/journal${action ? `?action=${encodeURIComponent(action)}` : ''}`, () => 20000)
  const catalogue = useApi<Liste<QuestionCatalogue>>('/catalogue')
  const questions = useMemo(
    () => Object.fromEntries((catalogue.donnees?.donnees ?? []).map((q) => [q.code, q.libelle])),
    [catalogue.donnees],
  )

  return (
    <>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-6">
        <div className="w-full max-w-sm">
          <Select libelle="Type d'événement" value={action} onChange={(e) => setAction(e.target.value)}>
            <option value="">Tous les événements</option>
            {Object.entries(LIBELLE_ACTION)
              .sort((a, b) => a[1].localeCompare(b[1], 'fr'))
              .map(([code, libelle]) => (
                <option key={code} value={code}>
                  {libelle}
                </option>
              ))}
          </Select>
        </div>
        <Case
          libelle="Masquer les connexions"
          checked={sansConnexions}
          onChange={(e) => setSansConnexions(e.target.checked)}
        />
      </div>
      <Ressource etat={etat} lignes={6}>
        {(r) => {
          const lignes = sansConnexions && action === '' ? r.donnees.filter((l) => l.action !== 'auth.connexion') : r.donnees
          return lignes.length === 0 ? (
            <Vide titre="Aucun événement" texte="Aucune ligne ne correspond à ce filtre." />
          ) : (
            <>
              <TableJournal lignes={lignes} questions={questions} avecSujet />
              <p className="mt-3 text-sm text-muted">
                Les 200 événements les plus récents. Les citoyens n'apparaissent que sous forme d'empreinte.
              </p>
            </>
          )
        }}
      </Ressource>
    </>
  )
}

function Integrite() {
  const { utilisateur } = useAuth()
  const points = useApi<Liste<PointDeControle>>('/journal/points-de-controle')
  const [verification, setVerification] = useState<VerificationJournal | null>(null)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur' | 'info'; texte: string } | null>(null)
  const [verif, setVerif] = useState(false)
  const [signature, setSignature] = useState(false)

  async function verifier() {
    setVerif(true)
    setMessage(null)
    try {
      setVerification(await api<VerificationJournal>('/journal/verifier-chaine', { method: 'POST' }))
    } catch (e) {
      setMessage({ ton: 'erreur', texte: messageErreur(e) })
    } finally {
      setVerif(false)
    }
  }

  async function signer() {
    setSignature(true)
    setMessage(null)
    try {
      const r = await api<{ cree: boolean }>('/journal/points-de-controle', { method: 'POST' })
      setMessage({
        ton: r.cree ? 'succes' : 'info',
        texte: r.cree ? 'Un point de contrôle a été signé.' : "Rien n'a changé depuis le dernier point de contrôle.",
      })
      points.recharger()
    } catch (e) {
      setMessage({ ton: 'erreur', texte: messageErreur(e) })
    } finally {
      setSignature(false)
    }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Panneau
        titre="Chaîne d'empreintes"
        description="Chaque ligne du journal contient l'empreinte de la précédente : en modifier une casse la chaîne."
      >
        <div className="space-y-4">
          <Bouton onClick={() => void verifier()} chargement={verif} icone={<ShieldCheck size={20} />}>
            Vérifier la chaîne
          </Bouton>
          <div aria-live="polite" className="space-y-3">
            {message && <Notice ton={message.ton}>{message.texte}</Notice>}
            {verification &&
              (verification.valide ? (
                <Notice ton="succes" titre="Journal intègre">
                  {verification.total} ligne(s) vérifiée(s)
                  {verification.points_de_controle !== undefined &&
                    `, ${verification.points_de_controle} point(s) de contrôle signé(s)`}
                  . Rien n'a été modifié ni supprimé.
                </Notice>
              ) : (
                <Notice ton="erreur" titre="Le journal a été altéré">
                  Ligne {verification.rupture?.id} :{' '}
                  {MOTIFS_RUPTURE[verification.rupture?.motif ?? ''] ?? verification.rupture?.motif}.
                </Notice>
              ))}
          </div>
        </div>
      </Panneau>

      <Panneau
        titre="Points de contrôle signés"
        description="Toutes les minutes, l'empreinte de tête du journal est signée. Même en recalculant toute la chaîne, on ne peut pas refaire ces signatures."
        actions={
          utilisateur?.role === 'administrateur' ? (
            <Bouton variante="secondaire" taille="s" onClick={() => void signer()} chargement={signature} icone={<PenNib size={18} />}>
              Signer maintenant
            </Bouton>
          ) : undefined
        }
      >
        <Ressource etat={points} lignes={3}>
          {(r) =>
            r.donnees.length === 0 ? (
              <p className="text-base text-muted">Aucun point de contrôle pour l'instant.</p>
            ) : (
              <ul className="divide-y divide-border">
                {r.donnees.slice(0, 8).map((p) => (
                  <li key={p.id} className="py-3 first:pt-0 last:pb-0">
                    <p className="text-base text-text">
                      Jusqu'à la ligne {p.dernier_id}, soit {p.total} ligne(s)
                    </p>
                    <p className="text-sm text-muted">
                      {dateHeure(p.cree_le)}, empreinte <span className="mono">{p.hash.slice(0, 16)}…</span>
                    </p>
                  </li>
                ))}
              </ul>
            )
          }
        </Ressource>
        <p className="mt-4 border-t border-border pt-4 text-sm text-muted">
          Ces points sont publics : quiconque en garde une copie peut détecter une réécriture de l'historique.
        </p>
      </Panneau>
    </div>
  )
}

export function Journal() {
  const [onglet, setOnglet] = useState<Onglet>('journal')

  return (
    <>
      <PageHeader
        titre="Journal et intégrité"
        description="Tout ce qui se passe dans le système, en ajout seul. Aucune donnée brute de citoyen n'y est jamais écrite."
      />
      <div className="mb-6">
        <Segmente<Onglet>
          legende="Affichage"
          valeur={onglet}
          onChange={setOnglet}
          options={[
            { valeur: 'journal', libelle: 'Événements' },
            { valeur: 'integrite', libelle: 'Intégrité' },
          ]}
        />
      </div>
      {onglet === 'journal' ? <Evenements /> : <Integrite />}
    </>
  )
}
