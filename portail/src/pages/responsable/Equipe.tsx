import { UserPlus } from '@phosphor-icons/react'
import { useState } from 'react'
import type { FormEvent } from 'react'
import { Bouton } from '../../components/Bouton'
import { Input } from '../../components/Champs'
import { Confirmation } from '../../components/Confirmation'
import { Dialogue } from '../../components/Dialogue'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { BadgeActif } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Tableau, Td, Th } from '../../components/Tableau'
import { ApiError, api, messageErreur } from '../../lib/api'
import { useAuth } from '../../lib/auth'
import { libelleRole } from '../../lib/format'
import type { Liste, MembreEquipe } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function Equipe() {
  const { utilisateur } = useAuth()
  const etat = useApi<Liste<MembreEquipe>>('/equipe')
  const [ajout, setAjout] = useState(false)
  const [nom, setNom] = useState('')
  const [email, setEmail] = useState('')
  const [motDePasse, setMotDePasse] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const [aRevoquer, setARevoquer] = useState<MembreEquipe | null>(null)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  function fermerAjout() {
    setAjout(false)
    setErreurs({})
  }

  async function creer(e: FormEvent) {
    e.preventDefault()
    const trouvees: Record<string, string> = {}
    if (!nom.trim()) trouvees.nom = 'Indiquez le nom de l’agent.'
    if (!email.trim()) trouvees.email = "Indiquez son adresse e-mail."
    if (motDePasse.length < 12) trouvees.mot_de_passe = 'Au moins 12 caractères.'
    setErreurs(trouvees)
    if (Object.keys(trouvees).length > 0) return

    setEnvoi(true)
    try {
      await api('/equipe', { method: 'POST', corps: { nom: nom.trim(), email: email.trim(), mot_de_passe: motDePasse } })
      setMessage({ ton: 'succes', texte: `${nom.trim()} peut maintenant se connecter.` })
      setNom('')
      setEmail('')
      setMotDePasse('')
      fermerAjout()
      etat.recharger()
    } catch (err) {
      if (err instanceof ApiError && Object.keys(err.erreurs).length > 0) {
        setErreurs(Object.fromEntries(Object.entries(err.erreurs).map(([k, v]) => [k, v[0]])))
      } else {
        setErreurs({ nom: messageErreur(err) })
      }
    } finally {
      setEnvoi(false)
    }
  }

  async function revoquer() {
    if (!aRevoquer) return
    setEnvoi(true)
    try {
      await api(`/equipe/${aRevoquer.id}/revoquer`, { method: 'POST' })
      setMessage({ ton: 'succes', texte: `${aRevoquer.nom} a perdu l'accès à l'instant, sur tous ses appareils.` })
      etat.recharger()
    } catch (err) {
      setMessage({ ton: 'erreur', texte: messageErreur(err) })
    } finally {
      setEnvoi(false)
      setARevoquer(null)
    }
  }

  return (
    <>
      <PageHeader
        titre="Équipe"
        description="Les agents de votre organisme. Une révocation coupe l'accès immédiatement."
        actions={
          <Bouton onClick={() => setAjout(true)} icone={<UserPlus size={20} />}>
            Ajouter un agent
          </Bouton>
        }
      />

      {message && (
        <div className="mb-6">
          <Notice ton={message.ton}>{message.texte}</Notice>
        </div>
      )}

      <Ressource etat={etat} lignes={3}>
        {(r) =>
          r.donnees.length === 0 ? (
            <Vide titre="Aucun agent" texte="Ajoutez les agents qui poseront des questions au nom de votre organisme." />
          ) : (
            <Tableau legende="Membres de l'équipe">
              <thead>
                <tr>
                  <Th>Nom</Th>
                  <Th className="hidden md:table-cell">Rôle</Th>
                  <Th>État</Th>
                  <Th>
                    <span className="sr-only">Actions</span>
                  </Th>
                </tr>
              </thead>
              <tbody>
                {r.donnees.map((m) => (
                  <tr key={m.id}>
                    <Td>
                      <span className="font-medium text-text">{m.nom}</span>
                      <span className="mt-0.5 block text-muted">{m.email}</span>
                    </Td>
                    <Td className="hidden md:table-cell">{libelleRole(m.role)}</Td>
                    <Td>
                      <BadgeActif actif={m.actif} />
                    </Td>
                    <Td className="text-right">
                      {m.actif && m.role === 'agent' && m.id !== utilisateur?.id && (
                        <Bouton variante="secondaire" taille="s" onClick={() => setARevoquer(m)}>
                          Révoquer
                        </Bouton>
                      )}
                    </Td>
                  </tr>
                ))}
              </tbody>
            </Tableau>
          )
        }
      </Ressource>

      <Dialogue
        ouvert={ajout}
        onFermer={fermerAjout}
        titre="Ajouter un agent"
        description="Il pourra poser des questions au nom de votre organisme."
        pied={
          <>
            <Bouton variante="secondaire" onClick={fermerAjout}>
              Annuler
            </Bouton>
            <Bouton type="submit" form="formulaire-agent" chargement={envoi}>
              Créer le compte
            </Bouton>
          </>
        }
      >
        <form id="formulaire-agent" onSubmit={creer} noValidate className="space-y-5">
          <Input libelle="Nom" value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} required />
          <Input
            libelle="Adresse e-mail"
            type="email"
            inputMode="email"
            autoComplete="off"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            erreur={erreurs.email}
            required
          />
          <Input
            libelle="Mot de passe provisoire"
            type="password"
            autoComplete="new-password"
            aide="Au moins 12 caractères. Communiquez-le à l'agent de vive voix."
            value={motDePasse}
            onChange={(e) => setMotDePasse(e.target.value)}
            erreur={erreurs.mot_de_passe}
            required
          />
        </form>
      </Dialogue>

      <Confirmation
        ouvert={aRevoquer !== null}
        titre="Révoquer cet agent ?"
        libelleAction="Révoquer l'agent"
        danger
        envoi={envoi}
        onConfirmer={() => void revoquer()}
        onFermer={() => setARevoquer(null)}
      >
        {aRevoquer && (
          <p>
            {aRevoquer.nom} perdra l'accès <strong className="font-semibold">immédiatement</strong>, sur tous ses
            appareils. Les demandes déjà posées restent consultables par votre organisme.
          </p>
        )}
      </Confirmation>
    </>
  )
}
