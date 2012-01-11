<?php
/**
 * LoansHelper класс
 *
 * @автор     art0s
 */
//==============================================================================
class LoansHelper {

	//----------------------------------------------------------------------
	/**
	 * Расчет размера аннуитетного платежа
	 *
	 * @param   [decimal]loan_sum		сумма кредита
	 * @param   [decimal]year_rate		процентная годовая ставка по кредиту
	 * @param   [integer]nomer_months	количество месяцев или платежей
	 * @return  decimal или NULL
	 */
	public static function annuity_payment( $loan_sum, $year_rate, $number_months )
	{
		// check the input arguments
		if( $loan_sum == 0 OR $loan_sum === null ) return 0.0;
		if( $year_rate == 0 OR $year_rate === null ) return 0.0;
		if( $number_months == 0 OR $number_months === null ) return null;

		$i = 0.0; $factor = 0.0; $A = 0.0; 	
		try {
			// rate for per month
			$i = $year_rate / 1200.0;

			// ratio of annuity
			$factor = $i * pow( 1 + $i, $number_months );
			$factor = $factor / ( pow( 1 + $i, $number_months ) - 1 );
	
			$A = $factor * $loan_sum;

			unset( $i );
			unset( $factor );
		}
		catch( Exception $e )
		{
			unset( $i );
			unset( $factor );
			return null;
		}
		
		return round( $A, 2 );	
	}

	//----------------------------------------------------------------------
	/**
	 * Формирует таблицу с графиком аннуитетных платежей по кредиту
	 *
	 * @param   [date   ]date_begin		дата начала кредитного периода
	 * @param   [decimal]loan_sum		сумма кредита
	 * @param   [decimal]year_rate		процентная годовая ставка по кредиту
	 * @param   [integer]nomer_months	количество месяцев или платежей
	 * @param   [integer]pay_day		календарный день оплаты в каждом месяце
	 * @return  array()
	 */
	public static function payments_graph_annuity( 	$date_begin,
							$loan_sum, $year_rate, $number_months, 
							$pay_day )
	{
		$graph = array( );

		// в начальном месяце не берем оплату
		$first_month = date( "Y-m", $date_begin );

		// календарный конец периода без учета выходных дней (субботы и воскресенья)
		$date_end = mktime( 0, 0, 0, 
					date( "m", $date_begin ) + $number_months, 
					$pay_day, 
					date( "Y", $date_begin ) );

		// конец периода с запасом, для корректной отработки случая
		// когда последний день периода выпал на выходной день
		$end_day = self::DateAdd( 'd', 2, $date_end );

                
		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		//
		// бежим по периоду для подсчета количества платежей
		//
		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		$cur_day = $date_begin;
		$counter = 0;

		while( $cur_day <= $end_day )
		{
			// итерация дня
			$cur_day = self::DateAdd( 'd', 1, $cur_day );

			if( date( "Y-m", $cur_day ) != $first_month )
			{
				$RET_CODE = self::is_pay_day( $cur_day, $pay_day, $date_end );

				if( $RET_CODE >= 1 ) $counter += 1; 
				if( $RET_CODE == 1000 ) break;
			}
		}

		// расчитаем месячный платеж
		$pay_size = self::annuity_payment( $loan_sum, $year_rate, $counter );

		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		//
		// начинаем формирование таблицы
		//
		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		// начальные значения
		$kred_rest = $loan_sum;
		$cur_day = $date_begin;
		$month_pay = 0.0;
		$days_count = 0;
		$procs_day = 0.0;
		$procs_all = 0.0;
		$idx = 0;

		// оформим выдачу кредита
		$graph[] = array( $idx, $cur_day, 0, $kred_rest, 0.0, $year_rate, -$kred_rest );

		while( $cur_day <= $end_day )
		{
			// итерация дня
			$cur_day = self::DateAdd( 'd', 1, $cur_day );
			$days_count += 1;

			// 1. процент за день
			$procs_day = $kred_rest * $year_rate / 100.0 / (date("L", $cur_day) == 1 ? 366.0 : 365.0);

			// 2. сборка процентов за период
			$procs_all += $procs_day;

			if( date( "Y-m", $cur_day ) != $first_month )
			{
				$RET_CODE = self::is_pay_day( $cur_day, $pay_day, $date_end );

				if( $RET_CODE >= 1 ) 
				{
					$idx += 1;

					// последний платеж?
					if( $idx == $counter ) 
					{
						$month_pay = $kred_rest;
						$procs_all = $pay_size - $month_pay;
					}
					else
					// вычислим размер платежа по основному долгу
					$month_pay = $pay_size - $procs_all;

					// вставим запись
					$graph[] = array( $idx, $cur_day, $days_count, $kred_rest, $month_pay, $procs_all, $pay_size );

					// оплата основного долга
					$kred_rest = $kred_rest - $month_pay;

					// последний платеж?
					// if( $idx == ($counter-1) ) $month_pay = $kred_rest;

					// обнулим данные 
					$days_count = 0;
					$procs_all = 0.0;
				}

				if( $RET_CODE == 1000 ) break;
			}
		}
		
		return $graph;	
	}

	//----------------------------------------------------------------------
	/**
	 * Формирует таблицу с графиком дифференцированных платежей по кредиту
	 *
	 * @param   [date   ]date_begin		дата начала кредитного периода
	 * @param   [decimal]loan_sum		сумма кредита
	 * @param   [decimal]year_rate		процентная годовая ставка по кредиту
	 * @param   [integer]nomer_months	количество месяцев или платежей
	 * @param   [integer]pay_day		календарный день оплаты в каждом месяце
	 * @return  array()
	 */
	public static function payments_graph_diff( 	$date_begin,
							$loan_sum, $year_rate, $number_months, 
							$pay_day )
	{
		$graph = array( );

		// в начальном месяце не берем оплату
		$first_month = date( "Y-m", $date_begin );

		// календарный конец периода без учета выходных дней (субботы и воскресенья)
		$date_end = mktime( 0, 0, 0, 
					date( "m", $date_begin ) + $number_months, 
					$pay_day, 
					date( "Y", $date_begin ) );

		// конец периода с запасом, для корректной отработки случая
		// когда последний день периода выпал на выходной день
		$end_day = self::DateAdd( 'd', 2, $date_end );
                
		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		//
		// бежим по периоду для подсчета количества платежей
		//
		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		$cur_day = $date_begin;
		$counter = 0;

		while( $cur_day <= $end_day )
		{
			// итерация дня
			$cur_day = self::DateAdd( 'd', 1, $cur_day );

			if( date( "Y-m", $cur_day ) != $first_month )
			{
				$RET_CODE = self::is_pay_day( $cur_day, $pay_day, $date_end );

				if( $RET_CODE >= 1 ) $counter += 1; 
				if( $RET_CODE == 1000 ) break;
			}
		}

		// расчитаем месячный платеж
		$month_pay = round( $loan_sum / $counter, 2 );

		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		//
		// начинаем формирование таблицы
		//
		//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		// начальные значения
		$kred_rest = $loan_sum;
		$cur_day = $date_begin;
		$days_count = 0;
		$procs_day = 0.0;
		$procs_all = 0.0;
		$idx = 0;

		// оформим выдачу кредита
		$graph[] = array( $idx, $cur_day, 0, $kred_rest, 0.0, $year_rate, -$kred_rest );

		while( $cur_day <= $end_day )
		{
			// итерация дня
			$cur_day = self::DateAdd( 'd', 1, $cur_day );
			$days_count += 1;

			// 1. процент за день
			$procs_day = $kred_rest * $year_rate / 100.0 / (date("L", $cur_day) == 1 ? 366.0 : 365.0);

			// 2. сборка процентов за период
			$procs_all += $procs_day;

			if( date( "Y-m", $cur_day ) != $first_month )
			{
				$RET_CODE = self::is_pay_day( $cur_day, $pay_day, $date_end );

				if( $RET_CODE >= 1 ) 
				{
					$idx += 1;

					// вставим запись
					$graph[] = array( $idx, $cur_day, $days_count, $kred_rest, $month_pay, $procs_all, $month_pay + $procs_all );

					// оплата основного долга
					$kred_rest = $kred_rest - $month_pay;

					// последний платеж?
					if( $idx == ($counter-1) ) $month_pay = $kred_rest;

					// обнулим данные 
					$days_count = 0;
					$procs_all = 0.0;
				}

				if( $RET_CODE == 1000 ) break;
			}
		}
		
		return $graph;	
	}

	//----------------------------------------------------------------------
	/**
	 * Является ли день рабочим (без учета праздничных дней, только вс.сб.)
	 *
	 * @param   [date]day		проверяемая дата
	 * @param   [int ]pay_day	календарный день оплаты в каждом месяце
	 * @param   [date]last_day	последний день кредитного договора
	 * @return  int
	 */
	private static function is_pay_day( $day, $pay_day, $last_day )
	{
		// если день выходной - то платежа не может быть в любом случае
		// для того что бы корректно обрабатывался конец периода - в цикле
		// расчета графика идет сверка с днем заведомо большим конца периода
		if( date( "w", $day ) == 6 OR date( "w", $day ) == 0) return 0;
		else
		{
			// день рабочий и день совпал - платить
			if( date( "j", $day ) == $pay_day ) 
			{
				// проверка на конец периода
				if( $day == $last_day ) return 1000;
				else return 1;
			}

			// день не совпал - проверим, может PAY_DAY был день или два назад
			// 1. прошлый день (воскресенье)
			$d = self::DateAdd( 'd', -1, $day );
                	if( date( "j", $d ) == $pay_day AND date( "w", $d ) == 0 ) 
			{
				// проверка на конец периода
				if( $day >= $last_day ) return 1000;
				else return 1;
			}

			// 2. прошлый день (суббота)
			$d = self::DateAdd( 'd', -2, $day );
                	if( date( "j", $d ) == $pay_day AND date( "w", $d ) == 6 ) 
			{
				// проверка на конец периода
				if( $day >= $last_day ) return 1000;
				else return 1;
			}
		}

		// по умолчанию - это не день платежа
		return 0;	
	}

	//----------------------------------------------------------------
	/**
	 * вычисляет коэффициент
	 *
	 * @param   [decimal]irr		текущая итераци
	 * @param   [array  ]graph		таблица с грaфиком платежей
	 * @return  decimal
	 */
        private static function psk_step( $irr, $graph )
        {
            $p = 0.0; $tmp = 0.0;

	    foreach( $graph as $row )
	    {
		$tmp = self::DateDiff( 'd', $graph[0][1], $row[1] ) / 365.0;
		$p = $p + $row[6] / pow( 1.0 + $irr, $tmp );
	    }

            return $p;
        }

	//----------------------------------------------------------------
	/**
	 * вычисляет полную стоимость кредита
	 *
	 * @param   [array]graph		таблица с графиком платежей
	 * @return  decimal
	 */
        public static function PSK( $graph )
        {            
		if( is_null($graph) ) return -1.0;
		if( count($graph) <= 1) return -1.0;

		$rate = $graph[0][5];
		$irr = $rate / 100.00; $p = 0.0; $delta = 0.00001;
                        
		// У НАС ЧАСТНЫЙ СЛУЧАЙ => поток > 0
		foreach( $graph as $row ) $p = $p + $row[6];
		if( $p < 0.0 ) return -1.0;

		while( $p >= 0 )
		{
			$irr += $delta;
			$p = self::psk_step( $irr, $graph );
		}

		return $irr * 100.0;
        }


	//----------------------------------------------------------------
	/**
	 * добавляет значение к дате
	 * оригинал:  http://www.phpbuilder.com/columns/argerich20030411.php3
	 *
	 * @param   [string ]interval		шаблон добавляемого значение
	 * @param   [integer]number		добавляемое значение
	 * @param   [date   ]date		дата к которой добавляется значение
	 * @return  date
	 */
	private static function DateAdd( $interval, $number, $date ) 
	{
		$date_time_array = getdate( $date );
		$hours = $date_time_array['hours'];
		$minutes = $date_time_array['minutes'];
		$seconds = $date_time_array['seconds'];
		$month = $date_time_array['mon'];
		$day = $date_time_array['mday'];
		$year = $date_time_array['year'];

		switch( $interval ) 
		{
			case 'yyyy':
			{
					$year += $number;
					break;
			}
			case 'q':
			{
					$year += ($number*3);
					break;
			}
			case 'm':
			{
					$month += $number;
					break;
			}
			case 'y':
			case 'd':
			case 'w':
			{
					$day += $number;
					break;
			}
			case 'ww':
			{
					$day += ($number*7);
					break;
			}
			case 'h':
			{
					$hours += $number;
					break;
			}
			case 'n':
			{
					$minutes += $number;
					break;
			}
			case 's':
			{
					$seconds+=$number; 
					break;            
			}
		}

		return mktime( $hours, $minutes, $seconds, $month, $day, $year );
	}
	//----------------------------------------------------------------
	/**
	 * вычисляет разницу дат
	 * оригинал:  http://www.phpbuilder.com/columns/argerich20030411.php3
	 *
	 * @param   [string]interval		шаблон значений
	 * @param   [date  ]date1		первая дата
	 * @param   [date  ]date2		вторая дата
	 * @return  date
	 */
	private static function DateDiff( $interval, $date1, $date2 ) 
	{
		// получает количество секунд между двумя датами 
		$timedifference = $date2 - $date1;

		switch( $interval ) 
		{
			case 'w': return bcdiv( $timedifference, 604800 );
			case 'd': return bcdiv( $timedifference, 86400 );
			case 'h': return bcdiv( $timedifference, 3600 );
			case 'n': return bcdiv( $timedifference, 60 );
			case 's': return $timedifference;
		}

		return $timedifference;
	}
	//----------------------------------------------------------------------

} // End LoansHelper 
//==============================================================================