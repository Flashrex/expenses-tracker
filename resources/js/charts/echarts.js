import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart } from 'echarts/charts';
import { GridComponent, LegendComponent, MarkLineComponent, TitleComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';
import { themes } from './theme';

echarts.use([PieChart, BarChart, LineChart, GridComponent, TitleComponent, TooltipComponent, LegendComponent, MarkLineComponent, SVGRenderer]);

Object.entries(themes).forEach(([name, theme]) => echarts.registerTheme(name, theme));

export { echarts };
