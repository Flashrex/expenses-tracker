import * as echarts from 'echarts/core';
import { BarChart, PieChart } from 'echarts/charts';
import { GridComponent, LegendComponent, TitleComponent, TooltipComponent } from 'echarts/components';
import { SVGRenderer } from 'echarts/renderers';
import { themes } from './theme';

echarts.use([PieChart, BarChart, GridComponent, TitleComponent, TooltipComponent, LegendComponent, SVGRenderer]);

Object.entries(themes).forEach(([name, theme]) => echarts.registerTheme(name, theme));

export { echarts };
