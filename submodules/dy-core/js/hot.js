const cellHeight = 23+2;
const headerHeight = 26+2;
	
const initGridsFromTextArea = hotDataFilter => {

	jQuery('[data-sensei-container]').each(function(){
	
		const {textareaId, containerId, maxId, isDisabled} = getDataSenseiIds(this);
				
		setTimeout(() => { 
			if(textareaId && containerId && maxId)
			{
				registerGrid({textareaId, containerId, maxId, isDisabled, hotDataFilter});
			}
		}, 1000);
	});
};


const getInitialGrid = ({rows, cols, columns}) => {

	return [...Array(rows).keys()].map(() => {

		return [...Array(cols).keys()].map((r, i) => {


			const {type} = columns[i];


			return (type === 'checkbox') ? false : '';
		})

	})

};

const registerGrid = ({textareaId, containerId, maxId, isDisabled, hotDataFilter}) => {	


	if(jQuery(textareaId).length === 0 || jQuery(containerId).length === 0)
	{
		return false;
	}
	

	// create an external HyperFormula instance
	const hyperformulaInstance = HyperFormula.buildEmpty({
		// to use an external HyperFormula instance,
		// initialize it with the `'internal-use-in-handsontable'` license key
		licenseKey: 'internal-use-in-handsontable',
	});


	//unescape textarea
	let data = {};
	let hasError = false;
	let content = jQuery('<textarea />').html(jQuery(textareaId).val()).text();
	let maxNum = parseInt(jQuery(maxId).val());
	const gridId = jQuery(containerId).attr('id');
	const grid = jQuery(containerId);
	const headers = getHeaders(containerId);
	const columns = getColType(containerId);
	const colsNum = headers.length;
	const defaultRows = getInitialGrid({rows: maxNum, cols: colsNum, columns});

	if(defaultRows.length === 0)
	{
		return false;
	}
	
	try
	{
		const parsedContent = JSON.parse(content);
		const firstDefaultRow = defaultRows[0];

		if(parsedContent.length === 0)
		{
			hasError = true;
			data = defaultRows;
		}

		if(parsedContent.hasOwnProperty(gridId))
		{
			if(parsedContent[gridId].length !== 0)
			{
				data = parsedContent[gridId];
				
				while (data.length < maxNum) {
					data.push(firstDefaultRow);
				}				

				data = data.slice(0, maxNum);

			}
			else
			{
				hasError = true;
				data = defaultRows;
			}
			
		}
		else
		{
			hasError = true;
			data = defaultRows;
		}
	}
	catch(e)
	{
		hasError = true;
		data = defaultRows;
	}
	
	if(hasError)
	{
		jQuery(textareaId).text(JSON.stringify({[gridId]: data}));
	}


    if(typeof hotDataFilter !== 'undefined')
    {
        //this function modifies data structure
        data = hotDataFilter({gridData: data, gridId});
    }

	const menu = {
		items: {
			undo: {
				name: 'undo'
			},
			redo : {
				name: 'redo'
			}
		}
	};

	const height = (maxNum > data.length) 
		? (cellHeight * maxNum)+ (headerHeight * 2) 
		: (cellHeight * data.length) + (headerHeight * 2);
	
	jQuery(containerId).height(height);
		
	const args = {
		licenseKey: 'non-commercial-and-evaluation',
		data: data,
		stretchH: 'all',
		columns: columns,
		startCols: colsNum,
		minCols: colsNum,
		rowHeaders: true,
		colHeaders: headers,
		readOnly: isDisabled,
		contextMenu: menu,
		minRows: maxNum,
		height,
		formulas: {
			engine: hyperformulaInstance,
			sheetName: containerId
		},
		afterChange: (changes, source) => {
			if (source !== 'loadData')
			{
				let gridData = grid.handsontable('getData');
				
				const maxNum = parseInt(jQuery(maxId).val());

				gridData = gridData.slice(0, maxNum);

				updateTextArea({textareaId, changes: gridData, containerId});
			}
		}
	}
				
	jQuery(grid).handsontable(args);

	const instance = jQuery(grid).handsontable('getInstance');
	
	jQuery(maxId).on('change', function() {

		if(jQuery(containerId).length === 0)
		{
			return false;
		}

		const thisField = jQuery(this);
		const maxNum = parseInt(jQuery(thisField).val());
		let rowNum = parseInt(jQuery(grid).handsontable('countRows'));
		let diff = 1;
		
		if(rowNum !== maxNum)
		{
			if(rowNum < maxNum)
			{
				diff = maxNum - rowNum;
				instance.alter('insert_row_below', rowNum, diff);
			}
			else
			{
				diff = rowNum - maxNum;

				instance.alter('remove_row', (rowNum-diff), diff);				
			}
		}
		
		let gridData = jQuery(grid).handsontable('getData');

		if(typeof hotDataFilter !== 'undefined')
		{
			gridData = hotDataFilter({gridData, gridId: gridId});
		}
		
		if(typeof gridData === 'undefined')
		{
			return false;
		}

		if(gridData.length > maxNum)
		{
			gridData = gridData.filter((v, i) => i+1 <= maxNum);
		}

		const height = (cellHeight * maxNum)+ (headerHeight * 2);
	
		jQuery(containerId).height(height);
		
		const textAreaData = updateTextArea({textareaId, changes: gridData, containerId});
		instance.updateSettings({maxRows: maxNum, data: textAreaData[gridId], height});
		instance.render();
	});		
}


const getHeaders = containerId => {
	let headers = jQuery(containerId).attr('data-sensei-headers');
	return headers.split(',');
}

const getColType = containerId => {
	let columns = jQuery(containerId).attr('data-sensei-type');
	columns = columns.replace(/\s+/g, '');
	columns = columns.split(',');
	let selectOption = null;
	const output = [];
	
	for(let x = 0; x < columns.length; x++)
	{
		let row = {};
		
		if(columns[x] == 'numeric')
		{
			row.type = 'numeric';
			row.format = '0';
		}
		else if(columns[x] == 'currency')
		{
			row.type = 'numeric';
			row.format = '0.00';
		}
		else if(columns[x] == 'date')
		{
			row.type = 'date';
			row.dateFormat = 'YYYY-MM-DD',
			row.correctFormat = true;
		}
		else if(columns[x] == 'dropdown')
		{
			selectOption = jQuery(containerId).attr('data-sensei-dropdown');
			selectOption = selectOption.replace(/\s+/g, '');
			selectOption = selectOption.split(',');
			row.type = 'dropdown';
			row.source = selectOption;
		}
		else if(columns[x] == 'readonly')
		{
			row.readOnly = true;
		}
		else if(columns[x] == 'checkbox')
		{
			row.type = 'checkbox';
			row.className = 'htCenter';
		}
		else
		{
			row.type = 'text';
		}
		
		output.push(row);
	}
	
	return output;	
}

const updateTextArea = ({textareaId, changes, containerId}) => {
	
	let output = {};
	const gridId = jQuery(containerId).attr('id');
	let oldData = jQuery('<textarea />').html(jQuery(textareaId).val()).text();

	try{
		oldData = JSON.parse(oldData);
	}
	catch(e)
	{
		console.log(e.message);
		console.log(oldData);
	}

	const height = (cellHeight * changes.length) + (headerHeight * 2);

	jQuery(containerId).height(height);
	output = {...oldData, [gridId]: changes};
	jQuery(textareaId).text(JSON.stringify(output));
	return output;
}



const getDefaultData = ({el, hotDataFilter}) => {

	const gridId = jQuery(el).attr('data-sensei-container');
	const maxId = jQuery(el).attr('data-sensei-max');
	const headers = jQuery(el).attr('data-sensei-headers').split(',');
	const maxNum = parseInt(jQuery(`#${maxId}`).val());

	let gridData = [...Array(maxNum).keys()].map(() => [...Array(headers.length).keys()].map(() => ''));
	let rows = (typeof hotDataFilter !== 'undefined') ? hotDataFilter({gridId, gridData}): gridData;

	return {[gridId]: rows};
};

const getDataFromTextarea = ({el, hotDataFilter}) => {
	const textAreaId = jQuery(el).attr('data-sensei-textarea');
	const gridId = jQuery(el).attr('data-sensei-container');
	let output = {[gridId]: getDefaultData({el, hotDataFilter})};
	let content = jQuery('<textarea />').html(jQuery(`#${textAreaId}`).val()).text();

	try
	{
		content = JSON.parse(content);

		if(content.hasOwnProperty(gridId))
		{
			output = content;
		}
	}
	catch(e)
	{
		console.log(e.message);
	}


	return output;
};



const getDataSenseiIds = obj => {
	const thisTextArea = jQuery(obj).attr('data-sensei-textarea');
	const disabled = jQuery(obj).attr('data-sensei-disabled');
	const thisMax = jQuery(obj).attr('data-sensei-max');
	const thisContainer = jQuery(obj).attr('data-sensei-container');

	const textareaId = (thisTextArea) ? `#${thisTextArea}` : null;
	const containerId = (thisContainer) ? `#${thisContainer}` : null;
	const isDisabled = (disabled === 'disabled') ? true : false;
	const maxId = (thisMax) ? `#${thisMax}`: null;

	return {textareaId, containerId, isDisabled, maxId};
}