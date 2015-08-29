/**
 * CRUDGenerator automaticaly creates CRUD form and display page.
 * Copyright (C) 2015 Cristiano Costa
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
var CRUDGenerator = {
	generateTable: function(fields, headers, data, table_id) {
		table = document.createElement("table");
		
		header_row = document.createElement("tr");
		for(h in headers) {
			header_col = document.createElement("th");
			header_col.innerHTML = headers[h];
			
			header_row.appendChild(header_col);
		}
		table.appendChild(header_row);
		
		for(d in data) {
			data_row = document.createElement("tr");
			for(c in data[d]) {
				data_col = document.createElement("td");
				data_col.innerHTML = data[d][c];
		
				data_row.appendChild(data_col);
			}
			table.appendChild(data_row);
		}
		
		document.getElementById(table_id).appendChild(table);
	}
}
