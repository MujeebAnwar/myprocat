<?php
require_once ('config.php');
require_once (DOCUMENT_ROOT.'/template/html_attributes.php');
class table_renderer extends content_block
{
	private $datareference = NULL;
	private $atr = NULL;
	private $column_names = array();

	public function __construct($datareference = NULL,$column_names = array(),$attributes = array())
	{
		$this->atr = new html_attributes();
		$this->datareference = &$datareference;
		$this->atr->set_attributes($attributes);
		$this->column_names = $column_names;
		parent::__construct(NULL,'table_renderer');
	}
	public function set_data($datareference)
	{
		$this->datareference = &$datareference;
	}
	public function set_attributes($attributes = array())
	{
		$this->atr->set_attributes($attributes);
	}
	public function set_columns($column_names = array())
	{
		$this->column_names = $column_names;
	}
	public function render()
	{
				print "\n<table";
				$atr->render_attributes("tbl_");
				print ">";
				if(count($data->results) > 0)
				{
					print "\n\t<tr";
					$atr->render_attributes("trh_");
					print ">";
					foreach($this->column_names AS $col)
					{
						print "<th";
						$atr->render_attributes("th_");
						print ">";
						
					}
					print "</tr>";
				}
				foreach($data->results AS $row)
				{
					print "\n\t<tr";
					$atr->render_attributes("tr_");
					print ">";
					foreach($this->column_names AS $col)
					{
						print "<td";
						$atr->render_attributes("td_");
						print ">";
						print $row[$col];
						print "</td>";

					}
					print "</tr>";

				}
				print "</table>";
	}
}
?>