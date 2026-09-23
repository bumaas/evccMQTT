<?php

declare(strict_types=1);

trait IdentHelper
{
    /**
     * Benennt den Ident einer bestehenden Variable um, bevor registerVariables() läuft - sonst
     * legte MaintainVariable eine zweite Variable an und die alte bliebe samt Archiv verwaist.
     * Verglichen wird exakt (nicht über IPS_GetObjectIDByIdent), weil die Umbenennungen hier
     * gerade die Groß-/Kleinschreibung betreffen - und Symcon beide Schreibweisen
     * nebeneinander zulässt.
     *
     * Gibt es den neuen Ident schon (eine ältere Modulversion hat ihn angelegt), wird nichts
     * umbenannt und nichts gelöscht: An der alten Variable können Archivdaten hängen. Ein
     * Log-Hinweis nennt sie, damit sie von Hand entfernt werden kann.
     *
     * @param array<string, string> $umbenennungen alter Ident => neuer Ident
     */
    private function migrateIdents(array $umbenennungen): void
    {
        $idents = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
            $idents[IPS_GetObject($id)['ObjectIdent']] = $id;
        }
        foreach ($umbenennungen as $alt => $neu) {
            if (!isset($idents[$alt]) || !IPS_VariableExists($idents[$alt])) {
                continue;
            }
            if (isset($idents[$neu])) {
                $this->LogMessage(sprintf(
                    'Variable #%d (Ident %s) wird nicht mehr genutzt, weil #%d (Ident %s) sie ersetzt. Sie kann gelöscht werden.',
                    $idents[$alt], $alt, $idents[$neu], $neu
                ), KL_WARNING);
                continue;
            }
            IPS_SetIdent($idents[$alt], $neu);
            $this->SendDebug(__FUNCTION__, sprintf('Ident %s -> %s (#%d)', $alt, $neu, $idents[$alt]), 0);
        }
    }
}
