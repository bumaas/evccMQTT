<?php

declare(strict_types=1);

trait IdentHelper
{
    /**
     * Benennt den Ident einer bestehenden Variable um, bevor registerVariables() läuft - sonst
     * legte MaintainVariable eine zweite Variable an und die alte bliebe samt Archiv verwaist.
     * Verglichen wird exakt (nicht über IPS_GetObjectIDByIdent), weil die Umbenennungen hier
     * gerade die Groß-/Kleinschreibung betreffen.
     *
     * @param array<string, string> $umbenennungen alter Ident => neuer Ident
     */
    private function migrateIdents(array $umbenennungen): void
    {
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $id) {
            $alt = IPS_GetObject($id)['ObjectIdent'];
            if (isset($umbenennungen[$alt]) && IPS_VariableExists($id)) {
                IPS_SetIdent($id, $umbenennungen[$alt]);
                $this->SendDebug(__FUNCTION__, sprintf('Ident %s -> %s (#%d)', $alt, $umbenennungen[$alt], $id), 0);
            }
        }
    }
}
