-- Ukol 2: zrychleni vypisu vlastniku
--
-- Bezpecne pro produkci:
--  - ALGORITHM=INPLACE, LOCK=NONE: index se stavi online, tabulky zustavaji citelne i zapisovatelne
--    (MariaDB 10.11). Pri ~1,2 mil. radku trva v radu sekund.
--  - IF NOT EXISTS: migraci lze spustit opakovane.
--  - Kod funguje i bez techto indexu (jen pomaleji), takze na poradi nasazeni kodu a migrace nezalezi.
--
-- Spusteni na existujici DB:
--   docker compose exec -T db mariadb -ucrm -pcrm legacy_crm < migrations/001_owners_list_indexes.sql

-- Pocet parcel vlastnika a jeho parcely (vypis, detail, filtr obce).
-- Bez indexu kazdy dotaz `where owner_id = ?` prochazel celou tabulku (1,2 mil. radku), a to 50x na strance.
ALTER TABLE ownerships
  ADD INDEX IF NOT EXISTS idx_ownerships_owner (owner_id, parcel_id),
  ALGORITHM=INPLACE, LOCK=NONE;

-- Vychozi razeni vypisu podle prijmeni a jmena (bez filesortu nad 500 tis. radky).
-- Filtr podle obce (join owners -> municipalities).
-- Hledani podle ICO (presna shoda). Bez nej by optimalizator kvuli razeni prochazel cely index jmen.
ALTER TABLE owners
  ADD INDEX IF NOT EXISTS idx_owners_name (last_name, first_name),
  ADD INDEX IF NOT EXISTS idx_owners_municipality (municipality_id),
  ADD INDEX IF NOT EXISTS idx_owners_ico (ico),
  ALGORITHM=INPLACE, LOCK=NONE;
