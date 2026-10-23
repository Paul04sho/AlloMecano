-- Recherche des mécanos (RG-R2 à RG-R6, RG-R9). Paramètres : position, rayon du palier, véhicule, panne.
SELECT m.id, m.public_id, m.prenom, m.nom, m.rayon_km, m.note_moyenne,
  ROUND(6371000 * 2 * ASIN(SQRT(
      POW(SIN(RADIANS(m.dispo_lat - :lat) / 2), 2)
    + COS(RADIANS(:lat)) * COS(RADIANS(m.dispo_lat)) * POW(SIN(RADIANS(m.dispo_lon - :lon) / 2), 2)
  ))) AS distance_m,
  EXISTS (SELECT 1 FROM mecanicien_specialites ms
          JOIN panne_specialite ps ON ps.specialite_id = ms.specialite_id
          WHERE ms.mecanicien_id = m.id AND ps.panne_id = :panne) AS specialiste,
  EXISTS (SELECT 1 FROM contacts c
          WHERE c.mecanicien_id = m.id AND c.canal = 'app' AND c.statut IN ('sonne','decroche')) AS en_appel,
  (co.rive IS NOT NULL AND co.rive <> :rive_client) AS autre_rive
FROM mecaniciens m
JOIN mecanicien_vehicules mv ON mv.mecanicien_id = m.id AND mv.type_vehicule = :vehicule
LEFT JOIN quartiers q  ON q.id = m.dispo_quartier_id
LEFT JOIN communes  co ON co.id = q.commune_id
WHERE m.statut_dossier = 'valide'
  AND m.etat = 'disponible'
  AND m.supprime_le IS NULL
  AND m.position_maj_le >= UTC_TIMESTAMP() - INTERVAL 12 HOUR
  -- pré-filtre rectangulaire : utilise l'index idx_meca_recherche avant le calcul exact
  AND m.dispo_lat BETWEEN :lat - :rayon / 111.32 AND :lat + :rayon / 111.32
  AND m.dispo_lon BETWEEN :lon - :rayon / (111.32 * COS(RADIANS(:lat)))
                      AND :lon + :rayon / (111.32 * COS(RADIANS(:lat)))
HAVING distance_m <= LEAST(:rayon, m.rayon_km) * 1000
ORDER BY en_appel ASC,
         distance_m * IF(specialiste, :bonus_specialiste, 1) * IF(autre_rive, :malus_autre_rive, 1) ASC,
         m.note_moyenne DESC
LIMIT :max_resultats;
