<?php



class tbxDatasource {

	/** @var tbx */				public $TBX;
	/** @var int|false */		public $Type			= false;
	/** @var int */				public $SubType			= TBX_DS_SUB0;
	/** @var mixed */			public $SrcId			= false;
	/** @var mixed */			public $RecSet			= false;
	/** @var int|string */		public $RecKey			= '';
	/** @var int */				public $RecNbr			= 0;
	/** @var int */				public $RecNum			= 0;
	/** @var int */				public $RecNumInit		= 0;
	/** @var bool */			public $RecSaving		= false; //TODO: SEE IF THIS IS USED
	/** @var bool */			public $RecSaved		= false; //TODO: SEE IF THIS IS USED
	/** @var array|false */		public $RecBuffer		= false;
	/** @var mixed */			public $CurrRec			= false;
	/** @var bool */			public $FirstRec		= true;

	//TEMPORARY TO SILENCE PHAN ERRORS
	/** @var mixed */			public $NumVal;
	/** @var mixed */			public $NumMin;
	/** @var mixed */			public $NumMax;
	/** @var mixed */			public $NumStep;
	/** @var mixed */			public $PrevRec;



	public function DataAlert($Msg) {
		throw new tbxException(
			$Msg .
			' when merging block ' .
			$this->TBX->_ChrOpen .
			(tbx_array($this->TBX->_CurrBlock)
				? implode(',', (array)$this->TBX->_CurrBlock)
				: $this->TBX->_CurrBlock) .
			$this->TBX->_ChrClose
		);
	}




	public function DataPrepare(&$SrcId, &$TBX) {
		$this->SrcId		= &$SrcId;
		$this->TBX			= &$TBX;
		$FctInfo			= false;
		$FctObj				= false;

		if (tbx_array($SrcId)) {
			$this->Type		= TBX_DS_ARRAY;

		} elseif (is_resource($SrcId)) {
			$FctInfo		= get_resource_type($SrcId);
			$FctCat			= 'r';

		} elseif (is_string($SrcId)) {
			switch (strtolower($SrcId)) {
				case 'array':	$this->Type = TBX_DS_ARRAY;	$this->SubType = TBX_DS_SUB1; break;
				case 'clear':	$this->Type = TBX_DS_ARRAY;	$this->SubType = TBX_DS_SUB3; break;
				case 'text':	$this->Type = TBX_DS_TEXT;		break;
				case 'num':		$this->Type = TBX_DS_NUMBER;	break;
				default:		$FctInfo = $SrcId; $FctCat = 'k';
			}

		} else if ($SrcId instanceof pudlResult) {
			$this->Type		= TBX_DS_PUDL;

		} elseif ($SrcId instanceof Iterator) {
			$this->Type		= TBX_DS_ITERATOR;
			$this->SubType	= TBX_DS_SUB1;

		} elseif ($SrcId instanceof ArrayObject) {
			$this->Type		= TBX_DS_ITERATOR;
			$this->SubType	= TBX_DS_SUB2;

		} elseif ($SrcId instanceof IteratorAggregate) {
			$this->Type		= TBX_DS_ITERATOR;
			$this->SubType	= TBX_DS_SUB3;

		} elseif (is_object($SrcId)) {
			$FctInfo		= get_class($SrcId);
			$FctCat			= 'o';
			$FctObj			= &$SrcId;
			$this->SrcId = &$SrcId;

		} elseif ($SrcId === false) {
			$SrcId			= [];
			$this->Type		= TBX_DS_ARRAY;

		} else {
			$this->DataAlert('unsupported variable type : \''.gettype($SrcId).'\'.');
		}

		if ($FctInfo!==false) {
			$ErrMsg = false;
			$this->Type		= $this->DataAlert($ErrMsg);
		}

		return ($this->Type !== false);
	}




	public function DataOpen(&$Query) {

		// Init values
		unset($this->CurrRec);
		$this->CurrRec = true;

		if ($this->RecSaved) {
			$this->FirstRec = true;

			unset($this->RecKey);
			$this->RecKey = '';

			$this->RecNum = $this->RecNumInit;

			return true;
		}

		unset($this->RecSet);
		$this->RecSet = false;

		$this->RecNumInit = 0;
		$this->RecNum = 0;

		switch ($this->Type) {
			case TBX_DS_ARRAY:
				if (($this->SubType===TBX_DS_SUB1) && (is_string($Query))) {
					$this->SubType = TBX_DS_SUB2;
				}

				if ($this->SubType===TBX_DS_SUB0) {
					$this->RecSet = &$this->SrcId;
				} elseif ($this->SubType===TBX_DS_SUB1) {
					if (tbx_array($Query)) {
						$this->RecSet = &$Query;
					} else {
						$this->DataAlert('type \''.gettype($Query).'\' not supported for the Query Parameter going with \'array\' Source Type.');
					}
				} elseif ($this->SubType===TBX_DS_SUB2) {
					// TBX query string for array and objects, syntax: "var[item1][item2]->item3[item4]..."
					$x = trim($Query);
					$z = chr(0);
					$x = str_replace(array(']->','][','->','['),$z,$x);
					if (substr($x,strlen($x)-1,1)===']') $x = substr($x,0,strlen($x)-1);
					$ItemLst = explode($z,$x);
					$ItemNbr = count($ItemLst);
					$Item0 = &$ItemLst[0];
					// Check first item
					if ($Item0[0]==='~') {
						$Item0 = substr($Item0,1);
						if ($this->TBX->ObjectRef!==false) {
							$Var = &$this->TBX->ObjectRef;
							$i = 0;
						} else {
							$i = $this->DataAlert('invalid query \''.$Query.'\' because property ObjectRef is not set.');
						}
					} else {
						$i = $this->DataAlert('invalid query \''.$Query.'\' because VarRef item \''.$Item0.'\' is not found.');
					}
					// Check sub-items
					$Empty = false;
					while (($i!==false) && ($i<$ItemNbr) && ($Empty===false)) {
						$x = $ItemLst[$i];
						if (tbx_array($Var)) {
							if (isset($Var[$x])) {
								$Var = &$Var[$x];
							} else {
								$Empty = true;
							}
						} elseif (is_object($Var)) {
							if (property_exists(get_class($Var), $x)) {
								$Var = &$Var->$x;
							} elseif (isset($Var->$x)) {
								$Var = $Var->$x; // useful for overloaded property
							} else {
								$Empty = true;
							}
						} else {
							$i = $this->DataAlert(
								'invalid query "' . $Query .
								'" because item "' . $ItemLst[$i] .
								'" is neither an Array nor an Object. Its type is "' .
								gettype($Var) . '".'
							);
						}
						if ($i!==false) $i++;
					}
					// Assign data
					if ($i!==false) {
						if ($Empty) {
							$this->RecSet = array();
						} else {
							$this->RecSet = &$Var;
						}
					}
				} elseif ($this->SubType===TBX_DS_SUB3) { // Clear
					$this->RecSet = array();
				}
				// First record
				if ($this->RecSet!==false) {
					$this->RecNbr = $this->RecNumInit + count($this->RecSet);
					$this->FirstRec = true;
					$this->RecSaved = true;
					$this->RecSaving = false;
				}
			break;


			case TBX_DS_NUMBER:
				$this->RecSet = true;
				$this->NumMin = 1;
				$this->NumMax = 1;
				$this->NumStep = 1;
				if (tbx_array($Query)) {
					if (isset($Query['min'])) $this->NumMin = $Query['min'];
					if (isset($Query['step'])) $this->NumStep = $Query['step'];
					if (isset($Query['max'])) {
						$this->NumMax = $Query['max'];
					} else {
						$this->RecSet = $this->DataAlert('the \'num\' source is an array that has no value for the \'max\' key.');
					}
					if ($this->NumStep==0) $this->RecSet = $this->DataAlert('the \'num\' source is an array that has a step value set to zero.');
				} else {
					$this->NumMax = ceil($Query);
				}
				if ($this->RecSet) {
					if ($this->NumStep>0) {
						$this->NumVal = $this->NumMin;
					} else {
						$this->NumVal = $this->NumMax;
					}
				}
			break;


			case TBX_DS_PUDL:
				case 1: $this->RecSet = &$this->SrcId;
			break;


			case TBX_DS_TEXT:
				$this->RecSet = &$Query;
			break;


			case TBX_DS_ITERATOR:
				if ($this->SubType==TBX_DS_SUB1) {
					$this->RecSet = $this->SrcId;
				} else {
					$this->RecSet = $this->SrcId->getIterator();
				}
				$this->RecSet->rewind();
			break;
		}

		if (($this->Type===TBX_DS_ARRAY) || ($this->Type===TBX_DS_ITERATOR)) {
			unset($this->RecKey); //IN CASE IT IS POINTER
			$this->RecKey = '';
		} else {
			if ($this->RecSaving) {
				unset($this->RecBuffer);
				$this->RecBuffer = [];
			}
			$this->RecKey = &$this->RecNum; // Not array: RecKey = RecNum
		}

		return ($this->RecSet!==false);
	}




	public function DataFetch() {

		if ($this->RecSaved) {
			if ($this->RecNum < $this->RecNbr) {
				if ($this->FirstRec) {
					if ($this->SubType === TBX_DS_SUB2) { // From string
						reset($this->RecSet);
						$this->RecKey		= key($this->RecSet);
						$this->CurrRec		= &$this->RecSet[$this->RecKey];

					} else if ($this->RecSet instanceof ArrayAccess) {
						unset($this->CurrRec);
						$this->RecKey		= '';
						$this->CurrRec		= false;
						foreach ($this->RecSet as $key => $val) {
							$this->RecKey	= $key;
							$this->CurrRec	= $val;
							break;
						}

					} else {
						$this->CurrRec		= reset($this->RecSet);
						$this->RecKey		= key($this->RecSet);
					}

					$this->FirstRec = false;

				} else {
					if ($this->SubType === TBX_DS_SUB2) { // From string
						next($this->RecSet);
						$this->RecKey		= key($this->RecSet);
						$this->CurrRec		= &$this->RecSet[$this->RecKey];

					} else if ($this->RecSet instanceof ArrayAccess) {
						unset($this->CurrRec);
						$this->RecKey		= '';
						$this->CurrRec		= false;
						$loop = 0;
						foreach ($this->RecSet as $key => $val) {
							$this->RecKey	= $key;
							$this->CurrRec	= $val;
							if ($loop++ === $this->RecNum) break;
						}

					} else {
						$this->CurrRec		= next($this->RecSet);
						$this->RecKey		= key($this->RecSet);
					}
				}

				if (!is_array($this->CurrRec) && (!is_object($this->CurrRec))) {
					$this->CurrRec = [
						'key' => $this->RecKey,
						'val' => $this->CurrRec
					];
				}

				$this->RecNum++;

			} else {
				unset($this->CurrRec);
				$this->CurrRec = false;
			}
			return;
		}

		switch ($this->Type) {
			case TBX_DS_PUDL:
				$this->CurrRec = $this->RecSet->row();
			break;

			case TBX_DS_NUMBER:
				if (($this->NumVal>=$this->NumMin) && ($this->NumVal<=$this->NumMax)) {
					$this->CurrRec = array('val'=>$this->NumVal);
					$this->NumVal += $this->NumStep;
				} else {
					$this->CurrRec = false;
				}
			break;

			case TBX_DS_TEXT:
				if ($this->RecNum===0) {
					if ($this->RecSet==='') {
						$this->CurrRec = false;
					} else {
						$this->CurrRec = &$this->RecSet;
					}
				} else {
					$this->CurrRec = false;
				}
			break;

			case TBX_DS_ITERATOR:
				if ($this->RecSet->valid()) {
					$this->CurrRec	= $this->RecSet->current();
					$this->RecKey	= $this->RecSet->key();
					$this->RecSet->next();
				} else {
					$this->CurrRec = false;
				}
			break;
		}

		// Set the row count
		if ($this->CurrRec!==false) {
			$this->RecNum++;
			if ($this->RecSaving) {
				$this->RecBuffer[$this->RecKey] = $this->CurrRec;
			}
		}
	}




	public function DataClose() {
		if ($this->RecSaved) return;

		if ($this->Type === TBX_DS_PUDL) {
			$this->RecSet->free();
		}

		if ($this->RecSaving) {
			$this->RecSet = &$this->RecBuffer;
			$this->RecNbr = $this->RecNumInit + count($this->RecSet);
			$this->RecSaving = false;
			$this->RecSaved = true;
		}
	}




	public function __debugInfo() {
		$return = [];
		foreach ($this as $key => $value) {
			if (!is_object($value)) {
				$return[$key] = $value;
			}
		}
		return $return;
	}
}
