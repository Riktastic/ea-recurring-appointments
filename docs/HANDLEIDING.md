# Handleiding: herhalende afspraken

Voor medewerkers en beheerders. Geen technische kennis nodig. (English: [USER_GUIDE.md](USER_GUIDE.md))

Een *herhalende afspraak* is een reeks gewone afspraken, bijvoorbeeld elke woensdag om 10:00 uur, tien weken lang.
Elke afspraak in de reeks is een echte afspraak: hij staat in de kalender, verstuurt de gebruikelijke e-mails en kan
los worden gewijzigd.

In de kalender staat bij afspraken die bij een reeks horen een **↻** voor de naam.

## Een herhalende afspraak maken

1. Kalender → **+** → **Afspraak**.
2. Vul de afspraak in zoals altijd: dienst, medewerker, klant, datum en tijd.
3. Zet **Deze afspraak herhalen** aan.
4. Kies hoe vaak:
   - **Wekelijks**: kies de weekdag(en) en *elke 1 week* (of elke 2, 3 … weken).
   - **Maandelijks op een weekdag**: bijvoorbeeld *de eerste maandag*.
   - **Maandelijks op een dagnummer**: bijvoorbeeld *de 15e*. Maanden zonder die dag (zoals de 31e in april) worden
     overgeslagen.
5. Kies wanneer het stopt: **Na** een aantal keer, of **Op datum**.
6. Optioneel: **Data bekijken** toont alle data en waarschuwt bij problemen.
7. Kies **Klant en medewerker informeren**: *Eén keer (eerste afspraak)* is meestal het beste.
8. Druk op **Opslaan**.

Als sommige data niet kunnen (de medewerker is vrij, heeft pauze of heeft al een afspraak), dan toont een venster die
data met de reden. Kies:

- **Conflicten overslaan**: alleen de vrije data boeken.
- **Toch boeken**: alle data boeken, ook die met een probleem (een dubbele boeking).
- **Annuleren**: niets boeken, zodat je eerst de tijd of het patroon kunt aanpassen.

Er wordt nooit dubbel geboekt zonder dat je zelf *Toch boeken* kiest.

## Een herhalende afspraak wijzigen

Klik op de afspraak en daarna op **Bewerk**. Bovenaan het venster staat bijvoorbeeld *"Afspraak 3 van 10 van een
herhalende afspraak (elke week op woensdag)"*.

Wijzig wat nodig is (tijd, duur, opmerkingen, medewerker …) en druk op:

| Knop | Wijzigt |
|---|---|
| **Opslaan** (onderaan het venster) | alleen deze afspraak |
| **Deze en volgende opslaan** | deze afspraak en alle latere in de reeks |
| **Alle opslaan** | alle afspraken in de reeks |

Verplaatsen naar een andere dag kan ook: zet de geopende afspraak van woensdag op donderdag en druk op **Alle
opslaan**, dan verhuist de hele reeks naar donderdag. Met **Deze en volgende opslaan** blijven de eerdere afspraken op
woensdag.

**Loskoppelen van reeks** maakt van deze ene afspraak een gewone afspraak. Latere wijzigingen aan de reeks laten hem
met rust.

## Verwijderen

Open in hetzelfde venster **Verwijder** en kies:

- **Alleen deze verwijderen**
- **Deze en volgende verwijderen**
- **Hele reeks verwijderen**

Je moet altijd eerst bevestigen. De gewone verwijderknop in het kleine pop-upvenster in de kalender verwijdert alleen
die ene afspraak.

## Klanten die een herhaling boeken (als de beheerder dit heeft aangezet)

Op de boekingspagina zien klanten onder de tijden **Herhalen** met *Niet herhalen*, *Elke week*, *Elke 2 weken*,
*Elke 4 weken* en *Elke maand (zelfde weekdag)*, en het aantal afspraken. Alleen tijden die echt vrij zijn worden
geboekt. Na het boeken ziet de klant welke data geboekt zijn en welke niet beschikbaar waren.

Deze afspraken staan in je kalender met de **↻** en zijn te wijzigen zoals elke andere reeks.

## Voor beheerders: instellingen

Instellingen → Boeking, onderdeel **Herhalende afspraken** onderaan. Druk daarna bovenaan de pagina op **Opslaan**.

- **Medewerkers kunnen herhalende afspraken maken in de kalender**: aan of uit voor medewerkers.
- **"Deze afspraak herhalen" standaard aanzetten bij nieuwe afspraken**: nieuwe afspraken beginnen met Herhalen aan.
- **Klanten kunnen op de boekingspagina een herhalende afspraak boeken**: toont de keuze Herhalen aan klanten.
- **Maximaal aantal afspraken per boeking**: het meeste dat een klant in één keer kan boeken (de eerste meegeteld).
- **Bevestigingsmails**: één e-mail per afspraak (elk met een eigen annuleerlink) of alleen voor de eerste.

Wie een reeks mag maken, wijzigen of verwijderen volgt de gewone rechten voor afspraken (Instellingen → Gebruikers →
Rollen).

## Meldingen die je kunt zien

| Melding | Betekenis |
|---|---|
| *Conflict met de planning van de medewerker op 2 van de 10 data* | Twee data kunnen niet; kies overslaan, toch boeken of annuleren. |
| *Alle data botsen met de planning van de medewerker* | Op dit tijdstip kan geen enkele datum. Kies een ander tijdstip. |
| *Het herhaalpatroon levert geen datum op voor het einde* | Controleer de gekozen weekdagen en de einddatum. |
| *Dit zou meer dan 260 afspraken opleveren* | Kies een eerdere einddatum of minder keer. |
| *Geen toestemming voor deze actie* | Je rol mag geen afspraken maken, wijzigen of verwijderen. Vraag een beheerder. |
| *De sessie is verlopen* | Herlaad de pagina en log opnieuw in. |
| *Deze afspraak hoort niet (meer) bij een herhalende reeks* | Iemand heeft hem losgekoppeld of verwijderd. Herlaad de kalender. |
| *De server gaf een onverwacht antwoord* | Herlaad de pagina en probeer het opnieuw. Blijft het gebeuren, dan kan een beheerder kijken in `storage/logs/` van de Easy!Appointments-installatie (regels die beginnen met "Recurring appointments"). |

## Mist er iets?

| Wat je ziet | Wat te doen |
|---|---|
| Geen schakelaar **Deze afspraak herhalen** | Die staat er alleen bij nieuwe afspraken, niet als je een bestaande opent. Controleer anders Instellingen → Boeking → *Medewerkers kunnen herhalende afspraken maken*. Na een update van Easy!Appointments draait de beheerder het installatieprogramma opnieuw. |
| Gele balk "nog niet ingesteld" op de kalender | Een beheerder klikt op **Nu instellen**. |
| Klanten zien de keuze Herhalen niet | Controleer Instellingen → Boeking → *Klanten kunnen op de boekingspagina een herhalende afspraak boeken*, en sla op. Bij het verzetten van een bestaande afspraak verschijnt de keuze nooit. |
| Minder afspraken dan gevraagd | Die data waren niet vrij. Het venster (medewerkers) of de bevestigingspagina (klanten) toont ze. |
