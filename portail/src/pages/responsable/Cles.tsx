import { CheckCircle, Key, Prohibit } from '@phosphor-icons/react'
import { useMemo, useState } from 'react'
import type { FormEvent } from 'react'
import { Bouton } from '../../components/Bouton'
import { BoutonCopier } from '../../components/BoutonCopier'
import { Case, Input, Textarea } from '../../components/Champs'
import { Confirmation } from '../../components/Confirmation'
import { Dialogue } from '../../components/Dialogue'
import { Ressource, Vide } from '../../components/Etats'
import { Notice } from '../../components/Notice'
import { Pastille } from '../../components/Pastille'
import { PageHeader } from '../../components/PageHeader'
import { Tableau, Td, Th } from '../../components/Tableau'
import { ApiError, api, messageErreur } from '../../lib/api'
import { dateLongue, ilYa } from '../../lib/format'
import type { CleApi, Liste, QuestionCatalogue } from '../../lib/types'
import { useApi } from '../../lib/useApi'

export function Cles() {
  const etat = useApi<Liste<CleApi>>('/organisme/cles')
  const catalogue = useApi<Liste<QuestionCatalogue>>('/catalogue')
  const [ouvert, setOuvert] = useState(false)
  const [nom, setNom] = useState('')
  const [choisies, setChoisies] = useState<string[]>([])
  const [quota, setQuota] = useState('')
  const [expiration, setExpiration] = useState('')
  const [clePublique, setClePublique] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)
  const [nouvelle, setNouvelle] = useState<string | null>(null)
  const [aRevoquer, setARevoquer] = useState<CleApi | null>(null)
  const [message, setMessage] = useState<{ ton: 'succes' | 'erreur'; texte: string } | null>(null)

  const questions = useMemo(() => catalogue.donnees?.donnees ?? [], [catalogue.donnees])

  function fermer() {
    setOuvert(false)
    setErreurs({})
  }

  async function creer(e: FormEvent) {
    e.preventDefault()
    if (!nom.trim()) {
      setErreurs({ nom: 'Donnez un nom à cette clé, par exemple le logiciel qui l’utilisera.' })
      return
    }
    setEnvoi(true)
    try {
      const r = await api<{ cle: string }>('/organisme/cles', {
        method: 'POST',
        corps: {
          nom: nom.trim(),
          questions: choisies.length > 0 ? choisies : undefined,
          quota_par_minute: quota ? Number(quota) : undefined,
          expire_le: expiration ? `${expiration} 23:59:59` : undefined,
          cle_publique: clePublique.trim() || undefined,
        },
      })
      setNouvelle(r.cle)
      setNom('')
      setChoisies([])
      setQuota('')
      setExpiration('')
      setClePublique('')
      fermer()
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
      await api(`/organisme/cles/${aRevoquer.id}/revoquer`, { method: 'POST' })
      setMessage({ ton: 'succes', texte: 'Clé révoquée : le logiciel qui l’utilisait est refusé dès maintenant.' })
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
        titre="Clés d'API"
        description="Pour que le logiciel de votre administration appelle KUMA BANA directement. Les mêmes règles s'appliquent qu'au portail."
        actions={
          <Bouton onClick={() => setOuvert(true)} icone={<Key size={20} />}>
            Créer une clé
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
            <Vide
              icone={Key}
              titre="Aucune clé d'API"
              texte="Créez une clé pour connecter un logiciel. Si vous enregistrez aussi une clé publique, chaque demande devra être signée."
            />
          ) : (
            <Tableau legende="Clés d'API de l'organisme">
              <thead>
                <tr>
                  <Th>Clé</Th>
                  <Th>État</Th>
                  <Th className="hidden md:table-cell">Portée</Th>
                  <Th className="hidden lg:table-cell">Dernier usage</Th>
                  <Th>
                    <span className="sr-only">Actions</span>
                  </Th>
                </tr>
              </thead>
              <tbody>
                {r.donnees.map((c) => (
                  <tr key={c.id}>
                    <Td>
                      <span className="font-medium text-text">{c.nom}</span>
                      <span className="mono mt-0.5 block text-muted">kb_{c.identifiant}_…</span>
                    </Td>
                    <Td>
                      {c.revoquee ? (
                        <Pastille ton="refus" icone={Prohibit}>Révoquée</Pastille>
                      ) : (
                        <Pastille ton="ok" icone={CheckCircle}>Active</Pastille>
                      )}
                    </Td>
                    <Td className="hidden text-muted md:table-cell">
                      {c.questions ? `${c.questions.length} question(s)` : 'Toutes vos questions'}
                      {c.signature_obligatoire && <span className="block">Demandes signées</span>}
                      {c.quota_par_minute && <span className="block">{c.quota_par_minute} par minute</span>}
                      {c.expire_le && <span className="block">Expire le {dateLongue(c.expire_le)}</span>}
                    </Td>
                    <Td className="hidden text-muted lg:table-cell">{c.dernier_usage_le ? ilYa(c.dernier_usage_le) : 'Jamais'}</Td>
                    <Td className="text-right">
                      {!c.revoquee && (
                        <Bouton variante="secondaire" taille="s" onClick={() => setARevoquer(c)}>
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
        ouvert={ouvert}
        onFermer={fermer}
        titre="Créer une clé d'API"
        pied={
          <>
            <Bouton variante="secondaire" onClick={fermer}>
              Annuler
            </Bouton>
            <Bouton type="submit" form="formulaire-cle" chargement={envoi}>
              Créer la clé
            </Bouton>
          </>
        }
      >
        <form id="formulaire-cle" onSubmit={creer} noValidate className="space-y-5">
          <Input libelle="Nom" aide="Par exemple le logiciel qui utilisera la clé." value={nom} onChange={(e) => setNom(e.target.value)} erreur={erreurs.nom} required />

          <fieldset>
            <legend className="text-sm font-medium text-text">Questions autorisées</legend>
            <p className="mb-2 text-sm text-muted">Ne cochez rien pour autoriser toutes les questions dont votre organisme a le droit.</p>
            <div className="max-h-48 overflow-y-auto rounded-control border border-border p-3">
              {questions.map((q) => (
                <Case
                  key={q.code}
                  libelle={q.libelle}
                  checked={choisies.includes(q.code)}
                  onChange={(e) => setChoisies((c) => (e.target.checked ? [...c, q.code] : c.filter((x) => x !== q.code)))}
                />
              ))}
            </div>
          </fieldset>

          <Input
            libelle="Demandes par minute"
            type="number"
            min={1}
            max={600}
            inputMode="numeric"
            aide="Facultatif. Par défaut : 60."
            value={quota}
            onChange={(e) => setQuota(e.target.value)}
            erreur={erreurs.quota_par_minute}
          />
          <Input
            libelle="Date d'expiration"
            type="date"
            aide="Facultatif."
            value={expiration}
            onChange={(e) => setExpiration(e.target.value)}
            erreur={erreurs.expire_le}
          />
          <Textarea
            libelle="Clé publique Ed25519 (base64)"
            aide="Facultatif. Si vous la fournissez, chaque demande devra être signée : une clé volée ne suffira plus."
            spellCheck={false}
            className="mono"
            value={clePublique}
            onChange={(e) => setClePublique(e.target.value)}
            erreur={erreurs.cle_publique}
          />
        </form>
      </Dialogue>

      <Dialogue
        ouvert={nouvelle !== null}
        onFermer={() => setNouvelle(null)}
        titre="Votre clé d'API"
        pied={<Bouton onClick={() => setNouvelle(null)}>J'ai conservé la clé</Bouton>}
      >
        <Notice ton="attention" titre="Copiez-la maintenant">
          Cette clé ne sera plus jamais affichée. Seule son empreinte est conservée.
        </Notice>
        <p className="mono rounded-control border border-border bg-surface-2 p-3 break-all text-text">{nouvelle}</p>
        {nouvelle && <BoutonCopier valeur={nouvelle} libelle="Copier la clé" />}
      </Dialogue>

      <Confirmation
        ouvert={aRevoquer !== null}
        titre="Révoquer cette clé ?"
        libelleAction="Révoquer la clé"
        danger
        envoi={envoi}
        onConfirmer={() => void revoquer()}
        onFermer={() => setARevoquer(null)}
      >
        <p>
          Le logiciel qui utilise « {aRevoquer?.nom} » sera refusé <strong className="font-semibold">immédiatement</strong>.
          Cette action est définitive : il faudra créer une nouvelle clé.
        </p>
      </Confirmation>
    </>
  )
}
